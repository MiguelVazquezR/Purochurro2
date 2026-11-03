<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use App\Services\RekognitionService; // Importar Servicio
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    // Inyección de dependencia del servicio AWS
    public function __construct(protected RekognitionService $rekognition) {}

    public function index(Request $request)
    {
        $query = Employee::query()->with(['media', 'user']); // Cargar User para la foto

        if ($request->search) {
            $query->where(function($q) use ($request) {
                $q->where('first_name', 'like', "%{$request->search}%")
                  ->orWhere('last_name', 'like', "%{$request->search}%");
            });
        }

        return Inertia::render('Employee/Index', [
            'employees' => $query->orderBy('is_active', 'desc')->latest()->paginate(10),
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        return Inertia::render('Employee/Create', [
            'availableBonuses' => Bonus::where('is_active', true)->orderBy('name')->get(),
            'shifts' => Shift::where('is_active', true)->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'email' => 'required|email|unique:users,email|unique:employees,email',
            'hired_at' => 'required|date',
            'base_salary' => 'required|numeric|min:0',
            'photo' => 'nullable|image|max:10240', // Validar imagen
            'default_schedule_template' => 'nullable|array',
            'recurring_bonuses' => 'nullable|array',
            'recurring_bonuses.*' => 'exists:bonuses,id',
        ]);

        DB::beginTransaction();
        try {
            // 1. Crear Usuario
            $user = User::create([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'email' => $validated['email'],
                'password' => Hash::make($validated['phone']), 
            ]);

            // 2. Manejo de FOTO en el USUARIO y REKOGNITION
            $awsFaceId = null;
            if ($request->hasFile('photo')) {
                // A. Guardar en User (Jetstream maneja el almacenamiento)
                $user->updateProfilePhoto($request->file('photo'));

                // B. Indexar en AWS Rekognition
                // Leemos el archivo temporal para enviarlo a AWS
                $imageBytes = file_get_contents($request->file('photo')->getRealPath());
                
                // Usamos el ID del User como referencia externa, o temporalmente null
                $awsFaceId = $this->rekognition->indexFace($imageBytes, (string)$user->id);
            }

            // 3. Crear Empleado
            $employeeData = $validated;
            $employeeData['user_id'] = $user->id;
            $employeeData['aws_face_id'] = $awsFaceId; // Guardar ID facial
            
            $employee = Employee::create($employeeData);

            // 4. Sincronizar Bonos
            if (!empty($validated['recurring_bonuses'])) {
                $employee->recurringBonuses()->sync($validated['recurring_bonuses']);
            }

            DB::commit();
            
            $msg = 'Empleado creado correctamente.';
            if ($request->hasFile('photo') && !$awsFaceId) {
                $msg .= ' (Advertencia: No se detectó rostro para reconocimiento facial)';
            }

            return redirect()->route('employees.index')->with('success', $msg);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function show(Employee $employee)
    {
        $employee->load(['media', 'user', 'vacationLogs', 'recurringBonuses']); 

        $vacationStats = [
            'years_service' => number_format($employee->years_of_service, 1),
            'total_days' => $employee->vacation_days_entitled,
            'available_days' => $employee->vacation_balance ?? $employee->vacation_days_entitled,
        ];

        $severanceData = null;
        if (auth()->id() === 1) {
            $severanceData = $this->calculateSeverancePreview($employee);
        }

        return Inertia::render('Employee/Show', [
            'employee' => $employee,
            'vacation_stats' => $vacationStats,
            'severance_data' => $severanceData,
            'shifts' => Shift::where('is_active', true)->get()
        ]);
    }

    public function edit(Employee $employee)
    {
        $employee->load(['media', 'recurringBonuses', 'user']);
        
        return Inertia::render('Employee/Edit', [
            'employee' => $employee,
            'availableBonuses' => Bonus::where('is_active', true)->orderBy('name')->get(),
            'shifts' => Shift::where('is_active', true)->get()
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        // Limpieza de datos de bonos
        if ($request->has('recurring_bonuses') && is_array($request->recurring_bonuses) && !empty($request->recurring_bonuses)) {
            $firstItem = $request->recurring_bonuses[0];
            if (is_array($firstItem) && isset($firstItem['id'])) {
                $request->merge([
                    'recurring_bonuses' => collect($request->recurring_bonuses)->pluck('id')->toArray()
                ]);
            }
        }

        // Reactivación
        if ($request->boolean('is_active') === true) {
            $request->merge([
                'termination_date' => null,
                'termination_reason' => null,
                'termination_notes' => null,
            ]);
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'birth_date' => 'required|date',
            'phone' => 'required|string|max:20',
            'address' => 'required|string',
            'base_salary' => 'required|numeric',
            'hired_at' => 'required|date',
            'default_schedule_template' => 'nullable|array',
            'email' => 'required|email|unique:employees,email,' . $employee->id,
            'password' => 'nullable|string|min:8',
            'photo' => 'nullable|image|max:10240',
            'remove_photo' => 'nullable|boolean',
            'recurring_bonuses' => 'nullable|array',
            'recurring_bonuses.*' => 'exists:bonuses,id',
            'is_active' => 'boolean',
            'termination_date' => 'nullable|date',
            'termination_reason' => 'nullable|string',
            'termination_notes' => 'nullable|string',
        ]);

        // Estado previo: lo necesitamos para detectar bajas y reactivaciones.
        $wasActive = (bool) $employee->is_active;

        DB::beginTransaction();
        try {
            $employee->update($validated);

            $warnings = [];
            $photoHandled = false;

            if ($employee->user) {
                $userData = [
                    'name' => "{$validated['first_name']} {$validated['last_name']}",
                    'email' => $validated['email'],
                ];

                if (!empty($validated['password'])) {
                    $userData['password'] = Hash::make($validated['password']);
                }

                $employee->user->update($userData);

                // --- GESTIÓN DE FOTO DE PERFIL Y REKOGNITION ---
                try {
                    if ($request->boolean('remove_photo')) {
                        // Sin foto tampoco debe quedar rostro en AWS.
                        $this->removeEmployeeFace($employee, $warnings);
                        $employee->user->deleteProfilePhoto();
                        $photoHandled = true;
                    }
                    elseif ($request->hasFile('photo')) {
                        // 1. Fuera el rostro anterior: AWS cobra por cada rostro guardado.
                        $this->removeEmployeeFace($employee, $warnings);

                        // 2. Guardar la nueva foto de perfil.
                        $employee->user->updateProfilePhoto($request->file('photo'));

                        // 3. Indexar el rostro nuevo.
                        $imageBytes = file_get_contents($request->file('photo')->getRealPath());
                        $newFaceId = $this->rekognition->indexFace($imageBytes, (string)$employee->user->id);
                        $employee->update(['aws_face_id' => $newFaceId]);
                        $photoHandled = true;

                        if (! $newFaceId) {
                            $warnings[] = 'No se detectó un rostro válido en la nueva foto; el empleado no podrá checar con reconocimiento facial hasta que se suba una foto adecuada.';
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("No se pudo sincronizar la foto/rostro del empleado {$employee->id}: " . $e->getMessage());
                    $warnings[] = 'No se pudo sincronizar el rostro en AWS; revisa los logs.';
                }
            }

            // --- ALTA / BAJA: el rostro solo debe existir mientras el empleado esté activo ---
            $this->syncFaceState($employee, $wasActive, $photoHandled, $warnings);

            if (isset($validated['recurring_bonuses'])) {
                $employee->recurringBonuses()->sync($validated['recurring_bonuses']);
            }
            
            DB::commit();

            $message = 'Empleado actualizado.';
            if (!empty($warnings)) {
                $message .= ' (Advertencia: ' . implode(' ', $warnings) . ')';
            }

            return redirect()->route('employees.index')->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error actualizando: ' . $e->getMessage());
        }
    }

    /**
     * Ajuste Manual de Vacaciones
     */
    public function adjustVacation(Request $request, Employee $employee)
    {
        if (auth()->id() !== 1) abort(403);

        $validated = $request->validate([
            'days' => 'required|numeric|not_in:0',
            'description' => 'required|string|max:255'
        ]);

        $employee->adjustVacationBalance(
            $validated['days'],
            'adjustment',
            $validated['description'],
            auth()->id()
        );

        return back()->with('success', 'Ajuste de vacaciones realizado.');
    }

    public function terminate(Request $request, Employee $employee)
    {
        if (auth()->id() !== 1) abort(403);

        $validated = $request->validate([
            'termination_date' => 'required|date',
            'reason' => 'required|in:justified,unjustified,resignation',
            'notes' => 'nullable|string'
        ]);

        $employee->update([
            'is_active' => false,
            'termination_date' => $validated['termination_date'],
            'termination_reason' => $validated['reason'],
            'termination_notes' => $validated['notes'] ?? null
        ]);

        // Baja: el rostro ya no se necesita y AWS cobra por cada rostro almacenado.
        $warnings = [];
        $this->removeEmployeeFace($employee, $warnings);

        $message = 'Baja procesada.';
        if (!empty($warnings)) {
            $message .= ' (Advertencia: ' . implode(' ', $warnings) . ')';
        }

        return back()->with('success', $message)->with('open_settlement', true);
    }

    public function destroy(Employee $employee)
    {
        $warnings = [];
        $this->removeEmployeeFace($employee, $warnings);

        $employee->delete();
        if ($employee->user) {
            $employee->user->delete();
        }

        $message = 'Empleado eliminado.';
        if (!empty($warnings)) {
            $message .= ' (Advertencia: ' . implode(' ', $warnings) . ')';
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * Elimina de AWS Rekognition el rostro registrado del empleado y limpia la columna local.
     *
     * No lanza excepciones: cualquier fallo se registra en el log y en $warnings.
     *
     * @param  array<int, string>  $warnings
     * @return bool True si AWS confirmó la eliminación (o el rostro ya no existía).
     */
    private function removeEmployeeFace(Employee $employee, array &$warnings = []): bool
    {
        $faceId = $employee->aws_face_id;
        $deleted = true;

        if ($faceId) {
            try {
                $deleted = $this->rekognition->deleteFace($faceId);
            } catch (\Throwable $e) {
                $deleted = false;
                Log::warning("Rekognition: error eliminando el rostro del empleado {$employee->id}: " . $e->getMessage());
            }
        }

        // La columna se limpia siempre: si AWS falló, el rostro queda huérfano
        // y lo puede barrer el comando rekognition:purge-orphan-faces.
        if ($employee->aws_face_id !== null) {
            $employee->forceFill(['aws_face_id' => null])->save();
        }

        if (! $deleted) {
            $warnings[] = 'AWS no confirmó la eliminación del rostro; se puede reintentar con "php artisan rekognition:purge-orphan-faces --delete".';
        }

        return $deleted;
    }

    /**
     * Sincroniza el rostro del empleado con su estado laboral.
     *
     * - Empleado dado de baja: se elimina el rostro (AWS cobra por rostro almacenado).
     * - Empleado activo sin rostro registrado (p. ej. al reactivarlo): se vuelve a
     *   indexar la foto de perfil que ya está guardada en el disco.
     *
     * @param  array<int, string>  $warnings
     */
    private function syncFaceState(Employee $employee, bool $wasActive, bool $photoHandled = false, array &$warnings = []): void
    {
        if (! $employee->is_active) {
            $this->removeEmployeeFace($employee, $warnings);
            return;
        }

        // Si en esta misma petición ya se indexó el rostro, no hay nada que hacer.
        if ($photoHandled || $employee->aws_face_id) {
            return;
        }

        try {
            $newFaceId = $this->indexFaceFromProfilePhoto($employee);

            if ($newFaceId) {
                $employee->update(['aws_face_id' => $newFaceId]);

                if (! $wasActive) {
                    Log::info("Rekognition: rostro reindexado tras reactivar al empleado {$employee->id}.");
                }

                return;
            }
        } catch (\Throwable $e) {
            Log::error("Rekognition: error reindexando el rostro del empleado {$employee->id}: " . $e->getMessage());
        }

        if ($employee->user?->profile_photo_path) {
            $warnings[] = 'No se pudo registrar el rostro del empleado para el reconocimiento facial; verifica que su foto de perfil muestre el rostro con claridad.';
        }
    }

    /**
     * Indexa en Rekognition la foto de perfil que ya está guardada en el disco.
     */
    private function indexFaceFromProfilePhoto(Employee $employee): ?string
    {
        $user = $employee->user;

        if (! $user || ! $user->profile_photo_path) {
            return null;
        }

        $disk = Storage::disk(config('jetstream.profile_photo_disk', 'public'));

        if (! $disk->exists($user->profile_photo_path)) {
            Log::warning("Rekognition: no se encontró la foto de perfil del usuario {$user->id} en el disco.");
            return null;
        }

        return $this->rekognition->indexFace($disk->get($user->profile_photo_path), (string) $user->id);
    }

    public function recommendation(Employee $employee)
    {
        $startDate = $employee->hired_at->translatedFormat('d F Y');
        $endDate = $employee->is_active 
            ? 'la fecha' 
            : ($employee->termination_date ? $employee->termination_date->translatedFormat('d F Y') : 'la fecha');

        return Inertia::render('Employee/Recommendation', [
            'employee' => $employee,
            'business' => [
                'name' => 'Puro Churro',
                'rep' => 'Sergio Gerardo García Arrizón',
            ],
            'period' => ['start' => $startDate, 'end' => $endDate],
            'date' => now()->translatedFormat('d F Y'),
        ]);
    }

    public function settlement(Employee $employee)
    {
        $data = $this->calculateSettlementCustom($employee);
        return Inertia::render('Employee/Settlement', [
            'employee' => $employee,
            'business' => ['name' => 'Puro Churro', 'address' => 'Av. Manuel Ávila Camacho 1950...'],
            'date' => now()->translatedFormat('d F Y'),
            'calculation' => $data,
        ]);
    }

    public function resignation(Employee $employee)
    {
        return Inertia::render('Employee/Resignation', [
            'employee' => $employee,
            'business' => ['name' => 'Puro Churro', 'address' => 'Av. Manuel Ávila Camacho 1950...'],
            'date' => now()->translatedFormat('d F Y'),
        ]);
    }

    public function contract(Request $request, Employee $employee, string $type)
    {
        if (!in_array($type, ['training', 'seasonal', 'indefinite'])) abort(404);

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : $employee->hired_at;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        return Inertia::render('Employee/Contract', [
            'employee' => $employee,
            'type' => $type,
            'shifts' => Shift::where('is_active', true)->get(),
            'business' => [
                'name' => 'Puro Churro',
                'rep' => 'Sergio Gerardo García Arrizón',
                'address' => 'Av. Manuel Ávila Camacho 1950...'
            ],
            'dates' => [
                'start' => $startDate->translatedFormat('d F Y'),
                'end' => $endDate ? $endDate->translatedFormat('d F Y') : null,
                'today' => now()->translatedFormat('d F Y'),
            ],
            'season_name' => $request->input('season_name'),
        ]);
    }

    public function acta(Request $request, Employee $employee)
    {
        return Inertia::render('Employee/Acta', [
            'employee' => $employee,
            'business' => ['name' => 'Puro Churro', 'address' => 'Av. Manuel Ávila Camacho 1950...'],
            'date' => [
                'full' => now()->translatedFormat('l j F Y'),
                'time' => now()->format('H:i'),
                'day' => now()->day,
                'month' => now()->translatedFormat('F'),
                'year' => now()->year,
            ],
            'motive' => $request->input('motive', 'Falta injustificada'),
            'description' => $request->input('description', 'Sin descripción adicional.'),
            'penalty_type' => $request->input('penalty_type', 'none'),
            'penalty_value' => $request->input('penalty_value', ''),
        ]);
    }

    /**
     * Lógica de cálculo de Finiquito y Liquidación Actualizada.
     * AGUINALDO: Basado en año calendario actual y días trabajados por semana.
     */
    private function calculateSeverancePreview(Employee $employee)
    {
        $dailySalary = $employee->base_salary; 
        $endDate = $employee->termination_date ?? now();
        $startDate = $employee->hired_at;
        $years = $startDate->floatDiffInYears($endDate);
        
        // --- 1. CÁLCULO DE AGUINALDO (CORREGIDO) ---
        
        // A) Determinar base anual según días trabajados por semana
        $schedule = $employee->default_schedule_template;
        $activeDaysPerWeek = 0;
        
        if (is_array($schedule)) {
            // Contamos los días que NO son nulos
            $activeDaysPerWeek = count(array_filter($schedule, fn($val) => !is_null($val)));
        }

        // Reglas de negocio:
        $annualAguinaldoDays = match ($activeDaysPerWeek) {
            6 => 12,
            5 => 10,
            4 => 8,
            3 => 6,
            2 => 2,
            1 => 2,
            default => 0, 
        };

        // B) Determinar días trabajados en el AÑO CALENDARIO actual
        $startOfCalendarYear = Carbon::create($endDate->year, 1, 1)->startOfDay();
        
        // Si fue contratado DESPUÉS del 1 de Enero de este año, usamos fecha contrato.
        // Si fue contratado ANTES, usamos 1 de Enero.
        $calculationStart = $startDate->gt($startOfCalendarYear) ? $startDate : $startOfCalendarYear;
        
        // Días efectivamente trabajados en el año actual (diffInDays es preciso para fechas completas)
        $daysWorkedThisCalendarYear = $calculationStart->diffInDays($endDate);

        // C) Cálculo Proporcional
        // (Días trabajados año / 365) * Días anuales que le tocan
        $aguinaldoDaysProportional = ($daysWorkedThisCalendarYear / 365) * $annualAguinaldoDays;
        $aguinaldoAmount = $aguinaldoDaysProportional * $dailySalary;


        // --- 2. CÁLCULO DE VACACIONES (MANTENIDO EN ANIVERSARIO O AJUSTAR SI ES NECESARIO) ---
        // Generalmente las vacaciones se deben desde el último aniversario.
        $lastAnniversary = $startDate->copy()->year($endDate->year);
        if ($lastAnniversary->gt($endDate)) $lastAnniversary->subYear();
        
        // Días desde el último aniversario
        $daysSinceAnniversary = $lastAnniversary->diffInDays($endDate);
        
        // Proporcional de vacaciones (Base legal mínima o regla negocio)
        // Aquí asumimos base de 6 días mínimo por año para el proporcional, según código previo.
        $vacationDaysProportional = ($daysSinceAnniversary / 365) * 6; 
        
        // Sumamos saldo pendiente que tenga guardado
        $totalVacationDays = $vacationDaysProportional + ($employee->vacation_balance ?? 0);
        $vacationAmount = $totalVacationDays * $dailySalary;

        $vacationPremium = $vacationAmount * 0.25;
        $finiquitoTotal = $aguinaldoAmount + $vacationAmount + $vacationPremium;

        // --- 3. LIQUIDACIÓN (Indemnización) ---
        $months3 = 90 * $dailySalary;
        $days20 = 20 * $years * $dailySalary;
        // Prima antigüedad: tope 2 salarios mínimos (aprox 540 o el real si es menor)
        $seniority = 12 * $years * min($dailySalary, 540);

        return [
            'daily_salary' => $dailySalary,
            'years_worked' => number_format($years, 2),
            'concepts' => [
                // Datos Aguinaldo
                'aguinaldo_proportional' => round($aguinaldoAmount, 2),
                'aguinaldo_days' => number_format($aguinaldoDaysProportional, 2),
                'aguinaldo_base_annual' => $annualAguinaldoDays, // Para referencia
                
                // Datos Vacaciones
                'vacations_proportional' => round($vacationAmount, 2),
                'vacation_days' => number_format($totalVacationDays, 2),
                'vacation_premium' => round($vacationPremium, 2),
                
                'total_finiquito' => round($finiquitoTotal, 2),
            ],
            'compensation_unjustified' => [
                'months_3' => round($months3, 2),
                'days_20_per_year' => round($days20, 2),
                'seniority_premium' => round($seniority, 2),
                'total_liquidation' => round($months3 + $days20 + $seniority + $finiquitoTotal, 2)
            ]
        ];
    }

    private function calculateSettlementCustom(Employee $employee)
    {
        $preview = $this->calculateSeverancePreview($employee);
        $aguinaldoBase = $preview['concepts']['aguinaldo_base_annual'];
        
        return [
            'daily_salary' => $preview['daily_salary'],
            'start_date' => $employee->hired_at->format('d/m/Y'),
            'end_date' => ($employee->termination_date ?? now())->format('d/m/Y'),
            'antiguedad_years' => $preview['years_worked'],
            'days_worked_year' => 'N/A', 
            'details' => [
                [
                    'concept' => "Parte Proporcional de Aguinaldo (Base $aguinaldoBase días/año)",
                    'days' => $preview['concepts']['aguinaldo_days'] . ' días',
                    'amount' => $preview['concepts']['aguinaldo_proportional']
                ],
                [
                    'concept' => 'Vacaciones Proporcionales (Pendientes + Proporcional)',
                    'days' => $preview['concepts']['vacation_days'] . ' días',
                    'amount' => $preview['concepts']['vacations_proportional']
                ],
                [
                    'concept' => 'Prima Vacacional (25%)',
                    'days' => 'N/A',
                    'amount' => $preview['concepts']['vacation_premium']
                ],
            ],
            'total' => $preview['concepts']['total_finiquito']
        ];
    }
}