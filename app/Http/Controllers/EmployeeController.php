<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\User; // Importamos User
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash; // Para la contraseña
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
            'employees' => $query->latest()->paginate(10),
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
            // El email es requerido si va a tener usuario
            'email' => 'required|email|unique:users,email|unique:employees,email',
            'hired_at' => 'required|date',
            'base_salary' => 'required|numeric|min:0',
            'photo' => 'nullable|image|max:10240',
            'default_schedule_template' => 'nullable|array',
        ]);

        DB::beginTransaction();
        try {
            // 1. Crear el Usuario para acceso al sistema
            // Usamos el teléfono como contraseña temporal
            $user = User::create([
                'name' => "{$validated['first_name']} {$validated['last_name']}",
                'email' => $validated['email'],
                'password' => Hash::make($validated['phone']), 
            ]);

            // 2. Crear el Empleado vinculado
            $employeeData = $validated;
            $employeeData['user_id'] = $user->id; // Vinculamos
            
            $employee = Employee::create($employeeData);

            if ($request->hasFile('photo')) {
                $employee->addMediaFromRequest('photo')
                    ->toMediaCollection('avatar');
            }

            DB::commit();
            return redirect()->route('employees.index')->with('success', 'Empleado y usuario creados correctamente.');

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creando empleado: ' . $e->getMessage());
            return back()->with('error', 'Ocurrió un error al guardar el empleado: ' . $e->getMessage());
        }
    }

    public function show(Employee $employee)
    {
        $employee->load(['media', 'user']); // Cargamos usuario también
        return Inertia::render('Employee/Show', [
            'employee' => $employee
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
            // Validar email único excluyendo al usuario actual si se permite cambiar
             'email' => 'required|email|unique:employees,email,' . $employee->id,
        ]);

        DB::beginTransaction();
        try {
            $employee->update($validated);

            // Actualizamos también el nombre/email del usuario relacionado si existe
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

    public function destroy(Employee $employee)
    {
        // Opcional: Decidir si borrar o desactivar el usuario login también
        // Por ahora solo borramos empleado (SoftDelete)
        $employee->delete();
        if ($employee->user) {
            $employee->user->delete(); // SoftDelete del usuario si User usa SoftDeletes, sino cuidado.
        }
        
        return redirect()->back()->with('success', 'Empleado dado de baja.');
    }
}