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
            // Enviamos los bonos activos para el selector
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
            // Validación para bonos recurrentes
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

            // Guardar Bonos Recurrentes
            if (!empty($validated['recurring_bonuses'])) {
                $employee->recurringBonuses()->sync($validated['recurring_bonuses']);
            }

            DB::commit();
            return redirect()->route('employees.index')->with('success', 'Empleado creado correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando empleado: ' . $e->getMessage());
            return back()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function show(Employee $employee)
    {
        $employee->load(['media', 'user', 'vacationLogs', 'recurringBonuses']); // Cargar bonos

        $vacationStats = [
            'years_service' => number_format($employee->years_of_service, 1),
            'total_days' => $employee->vacation_days_entitled,
            'available_days' => $employee->vacation_balance ?? $employee->vacation_days_entitled,
        ];

        $severanceData = null;
        if (auth()->id() === 1) {
            $severanceData = $this->calculateSeveranceMexico($employee);
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
        $employee->load(['media', 'recurringBonuses']); // Cargar bonos actuales
        
        return Inertia::render('Employee/Edit', [
            'employee' => $employee,
            'availableBonuses' => Bonus::where('is_active', true)->orderBy('name')->get(),
            'shifts' => Shift::where('is_active', true)->get()
        ]);
    }

    public function update(Request $request, Employee $employee)
    {
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

        return back()->with('success', 'Empleado dado de baja correctamente.');
    }

    public function destroy(Employee $employee)
    {
        $employee->delete();
        if ($employee->user) {
            $employee->user->delete();
        }
        return redirect()->back()->with('success', 'Empleado eliminado.');
    }

    private function calculateSeveranceMexico(Employee $employee)
    {
        $dailySalary = $employee->base_salary; 
        $years = $employee->years_of_service;
        
        $daysWorkedThisYear = $employee->hired_at->diffInDays(now()) % 365; 
        $proportionalAguinaldo = ($daysWorkedThisYear / 365) * 15 * $dailySalary;

        $vacationDays = $employee->vacation_days_entitled; 
        $proportionalVacations = ($daysWorkedThisYear / 365) * $vacationDays * $dailySalary;
        $vacationPremium = $proportionalVacations * 0.25;

        $settlement = $proportionalAguinaldo + $proportionalVacations + $vacationPremium;

        $severance = 90 * $dailySalary;
        $compensation20Days = 20 * $years * $dailySalary;

        $minWageCap = 540;
        $salaryForPremium = min($dailySalary, $minWageCap);
        $seniorityPremium = 12 * $years * $salaryForPremium;

        return [
            'daily_salary' => $dailySalary,
            'years_worked' => number_format($years, 2),
            'concepts' => [
                'aguinaldo_proportional' => round($proportionalAguinaldo, 2),
                'vacations_proportional' => round($proportionalVacations, 2),
                'vacation_premium' => round($vacationPremium, 2),
                'total_finiquito' => round($settlement, 2),
            ],
            'compensation_unjustified' => [
                'months_3' => round($severance, 2),
                'days_20_per_year' => round($compensation20Days, 2),
                'seniority_premium' => round($seniorityPremium, 2),
                'total_liquidation' => round($severance + $compensation20Days + $seniorityPremium + $settlement, 2)
            ]
        ];
    }
}