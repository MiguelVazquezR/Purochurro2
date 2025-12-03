<?php

namespace App\Http\Controllers;

use App\Models\Employee;
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
        return Inertia::render('Employee/Create');
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
        $employee->load(['media', 'user']);

        // Stats de Vacaciones
        $vacationStats = [
            'years_service' => number_format($employee->years_of_service, 1),
            'total_days' => $employee->vacation_days_entitled,
            'available_days' => $employee->vacation_balance ?? $employee->vacation_days_entitled, // Usamos balance si existe
        ];

        $severanceData = null;

        // Solo Admin (ID 1) ve el cálculo
        if (auth()->id() === 1) {
            $severanceData = $this->calculateSeveranceMexico($employee);
        }

        return Inertia::render('Employee/Show', [
            'employee' => $employee,
            'vacation_stats' => $vacationStats,
            'severance_data' => $severanceData
        ]);
    }

    public function edit(Employee $employee)
    {
        $employee->load('media');
        return Inertia::render('Employee/Edit', [
            'employee' => $employee
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
            
            DB::commit();
            return redirect()->route('employees.index')->with('success', 'Empleado actualizado.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error actualizando: ' . $e->getMessage());
        }
    }

    // Método para DAR DE BAJA (Inactivar + Datos de Salida)
    public function terminate(Request $request, Employee $employee)
    {
        // Solo admin (ID 1)
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

        // Opcional: Bloquear acceso al sistema
        if ($employee->user) {
            // Podrías borrarlo o banearlo. Aquí solo ejemplo:
            // $employee->user->delete(); 
        }

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

    /**
     * Calculadora de Finiquito y Liquidación (Ley México)
     */
    private function calculateSeveranceMexico(Employee $employee)
    {
        // Asumimos que base_salary es el salario diario para el cálculo
        $dailySalary = $employee->base_salary; 
        $years = $employee->years_of_service;
        
        // 1. Aguinaldo Proporcional (15 días por año)
        $daysWorkedThisYear = $employee->hired_at->diffInDays(now()) % 365; 
        $proportionalAguinaldo = ($daysWorkedThisYear / 365) * 15 * $dailySalary;

        // 2. Vacaciones Proporcionales + Prima (25%)
        $vacationDays = $employee->vacation_days_entitled; 
        $proportionalVacations = ($daysWorkedThisYear / 365) * $vacationDays * $dailySalary;
        $vacationPremium = $proportionalVacations * 0.25;

        // FINIQUITO BASE
        $settlement = $proportionalAguinaldo + $proportionalVacations + $vacationPremium;

        // LIQUIDACIÓN (Despido Injustificado)
        $severance = 0;
        $compensation20Days = 0;
        $seniorityPremium = 0;

        // Simulamos cálculo completo para el admin
        $severance = 90 * $dailySalary; // 3 meses
        $compensation20Days = 20 * $years * $dailySalary; // 20 días por año

        // Prima Antigüedad (12 días por año, tope 2x Salario Mínimo)
        $minWageCap = 540; // Tope aprox 2025
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