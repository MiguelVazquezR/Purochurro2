<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use App\Models\Employee;
use App\Models\Shift;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::query()->with('media');

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
            'photo' => 'nullable|image|max:10240',
            'default_schedule_template' => 'nullable|array',
            'recurring_bonuses' => 'nullable|array',
            'recurring_bonuses.*' => 'exists:bonuses,id',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'email' => $validated['email'],
                'password' => Hash::make($validated['phone']), 
            ]);

            $employeeData = $validated;
            $employeeData['user_id'] = $user->id;
            
            $employee = Employee::create($employeeData);

            if ($request->hasFile('photo')) {
                $employee->addMediaFromRequest('photo')->toMediaCollection('avatar');
            }

            if (!empty($validated['recurring_bonuses'])) {
                $employee->recurringBonuses()->sync($validated['recurring_bonuses']);
            }

            DB::commit();
            return redirect()->route('employees.index')->with('success', 'Empleado creado correctamente.');

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

        // Calculamos datos de baja para el modal (Admin only) usando la lógica del recibo
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
        $employee->load(['media', 'recurringBonuses']);
        
        return Inertia::render('Employee/Edit', [
            'employee' => $employee,
            'availableBonuses' => Bonus::where('is_active', true)->orderBy('name')->get(),
            'shifts' => Shift::where('is_active', true)->get()
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
        // --- CORRECCIÓN: Limpieza de datos antes de validar ---
        
        // 1. Si los bonos vienen como objetos (desde Vue), extraemos solo los IDs
        if ($request->has('recurring_bonuses') && is_array($request->recurring_bonuses) && !empty($request->recurring_bonuses)) {
            // Verificamos si el primer elemento es un array/objeto con ID
            $firstItem = $request->recurring_bonuses[0];
            if (is_array($firstItem) && isset($firstItem['id'])) {
                $request->merge([
                    'recurring_bonuses' => collect($request->recurring_bonuses)->pluck('id')->toArray()
                ]);
            }
        }

        // 2. Si se está reactivando, limpiamos campos de baja
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
            // Validación Bonos
            'recurring_bonuses' => 'nullable|array',
            'recurring_bonuses.*' => 'exists:bonuses,id',
            // Campos de Estado (Necesarios para reactivar)
            'is_active' => 'boolean',
            'termination_date' => 'nullable|date',
            'termination_reason' => 'nullable|string',
            'termination_notes' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $employee->update($validated);

            if ($employee->user) {
                $employee->user->update([
                    'name' => "{$validated['first_name']} {$validated['last_name']}",
                    'email' => $validated['email'],
                ]);
            }

            if ($request->hasFile('photo')) {
                $employee->clearMediaCollection('avatar'); 
                $employee->addMediaFromRequest('photo')->toMediaCollection('avatar');
            }

            // Sincronizar Bonos Recurrentes
            if (isset($validated['recurring_bonuses'])) {
                $employee->recurringBonuses()->sync($validated['recurring_bonuses']);
            }
            
            DB::commit();
            return redirect()->route('employees.index')->with('success', 'Empleado actualizado.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error actualizando: ' . $e->getMessage());
        }
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
            'termination_notes' => $validated['notes']
        ]);

        // Redirigimos de vuelta con señal para abrir finiquito
        return back()->with('success', 'Baja procesada.')->with('open_settlement', true);
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        if ($employee->user) {
            $employee->user->delete();
        }
        return redirect()->back()->with('success', 'Empleado eliminado.');
    }

    // --- GENERACIÓN DE DOCUMENTOS ---

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
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
            'date' => now()->translatedFormat('d F Y'),
        ]);
    }

    public function settlement(Employee $employee)
    {
        $data = $this->calculateSettlementCustom($employee);

        return Inertia::render('Employee/Settlement', [
            'employee' => $employee,
            'business' => [
                'name' => 'Puro Churro',
                'address' => 'Av. Manuel Ávila Camacho 1950, interior Plaza Patria, Zapopan, Jalisco.',
            ],
            'date' => now()->translatedFormat('d F Y'),
            'calculation' => $data,
        ]);
    }

    public function resignation(Employee $employee)
    {
        return Inertia::render('Employee/Resignation', [
            'employee' => $employee,
            'business' => [
                'name' => 'Puro Churro',
                'address' => 'Av. Manuel Ávila Camacho 1950, interior Plaza Patria, Zapopan, Jalisco.',
            ],
            'date' => now()->translatedFormat('d F Y'),
        ]);
    }

    public function contract(Request $request, Employee $employee, string $type)
    {
        if (!in_array($type, ['training', 'seasonal', 'indefinite'])) {
            abort(404);
        }

        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : $employee->hired_at;
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : null;

        $dates = [
            'start' => $startDate->translatedFormat('d F Y'),
            'end' => $endDate ? $endDate->translatedFormat('d F Y') : null,
            'today' => now()->translatedFormat('d F Y'),
        ];

        return Inertia::render('Employee/Contract', [
            'employee' => $employee,
            'type' => $type,
            'shifts' => Shift::where('is_active', true)->get(),
            'business' => [
                'name' => 'Puro Churro',
                'rep' => 'Sergio Gerardo García Arrizón',
                'address' => 'Av. Manuel Ávila Camacho 1950, interior plaza patria, isla comercial en frente de negocio comercial conocido como Aldo Conti, en el área común, col. Jacarandas, Zapopan, Jalisco',
            ],
            'dates' => $dates,
            'season_name' => $request->input('season_name'),
        ]);
    }

    public function acta(Request $request, Employee $employee)
    {
        $business = [
            'name' => 'Puro Churro',
            'address' => 'Av. Manuel Ávila Camacho 1950, interior Plaza Patria, Zapopan, Jalisco.',
        ];

        $now = now();
        $dateDetails = [
            'full' => $now->translatedFormat('l j F Y'),
            'time' => $now->format('H:i'),
            'day' => $now->day,
            'month' => $now->translatedFormat('F'),
            'year' => $now->year,
        ];

        return Inertia::render('Employee/Acta', [
            'employee' => $employee,
            'business' => $business,
            'date' => $dateDetails,
            'motive' => $request->input('motive', 'Falta injustificada'),
            'description' => $request->input('description', 'Sin descripción adicional.'),
            'penalty_type' => $request->input('penalty_type', 'none'),
            'penalty_value' => $request->input('penalty_value', ''),
        ]);
    }

    // --- LÓGICA DE CÁLCULO ---

    /**
     * Cálculo para la VISTA PREVIA (Modal en Show.vue) y base para PDF.
     * Incluye desglose detallado de días y montos.
     */
    private function calculateSeverancePreview(Employee $employee)
    {
        $dailySalary = $employee->base_salary; 
        $endDate = $employee->termination_date ?? now();
        $startDate = $employee->hired_at;
        $years = $startDate->floatDiffInYears($endDate);
        
        // Días trabajados año actual
        $lastAnniversary = $startDate->copy()->year($endDate->year);
        if ($lastAnniversary->gt($endDate)) $lastAnniversary->subYear();
        $daysWorkedThisYear = $lastAnniversary->diffInDays($endDate);

        // 1. Aguinaldo Proporcional (15 días por año)
        $aguinaldoDays = ($daysWorkedThisYear / 365) * 15;
        $aguinaldoAmount = $aguinaldoDays * $dailySalary;

        // 2. Vacaciones Proporcionales (REGLA NEGOCIO: 6 días fijos)
        $vacationDaysProportional = ($daysWorkedThisYear / 365) * 6;
        $totalVacationDays = $vacationDaysProportional + ($employee->vacation_balance ?? 0);
        $vacationAmount = $totalVacationDays * $dailySalary;

        // 3. Prima Vacacional (25%)
        $vacationPremium = $vacationAmount * 0.25;

        $finiquitoTotal = $aguinaldoAmount + $vacationAmount + $vacationPremium;

        // Indemnización (Para despido injustificado)
        $months3 = 90 * $dailySalary;
        $days20 = 20 * $years * $dailySalary;
        $seniority = 12 * $years * min($dailySalary, 540); // Tope salarial prima antigüedad

        return [
            'daily_salary' => $dailySalary,
            'years_worked' => number_format($years, 2),
            'concepts' => [
                'aguinaldo_proportional' => round($aguinaldoAmount, 2),
                'aguinaldo_days' => number_format($aguinaldoDays, 2),
                
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

    /**
     * Cálculo para el DOCUMENTO PDF (Recibo).
     * Reutiliza la lógica del preview para asegurar consistencia.
     */
    private function calculateSettlementCustom(Employee $employee)
    {
        $preview = $this->calculateSeverancePreview($employee);
        
        return [
            'daily_salary' => $preview['daily_salary'],
            'start_date' => $employee->hired_at->format('d/m/Y'),
            'end_date' => ($employee->termination_date ?? now())->format('d/m/Y'),
            'antiguedad_years' => $preview['years_worked'],
            'days_worked_year' => 'N/A', 
            'details' => [
                [
                    'concept' => 'Parte Proporcional de Aguinaldo',
                    'days' => $preview['concepts']['aguinaldo_days'] . ' días',
                    'amount' => $preview['concepts']['aguinaldo_proportional']
                ],
                [
                    'concept' => 'Vacaciones Proporcionales (Base 6 días)',
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