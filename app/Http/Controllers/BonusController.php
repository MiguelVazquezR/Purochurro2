<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BonusController extends Controller
{
    public function index()
    {
        return Inertia::render('Bonus/Index', [
            'bonuses' => Bonus::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'type' => 'required|string|in:fixed,percentage', 
        ]);

        Bonus::create($validated);

        return back()->with('success', 'Bono creado correctamente.');
    }

    public function update(Request $request, Bonus $bonus)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $bonus->update($validated);
        return back()->with('success', 'Bono actualizado.');
    }

    public function destroy(Bonus $bonus)
    {
        $bonus->delete();
        return back()->with('success', 'Bono eliminado.');
    }

    /**
     * Asigna un bono existente a un empleado en una fecha específica.
     * Esto llena la tabla pivote 'employee_bonus'.
     */
    public function assign(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'bonus_id' => 'required|exists:bonuses,id',
            'assigned_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0', // Permite sobreescribir el monto por defecto
        ]);

        $bonus = Bonus::findOrFail($validated['bonus_id']);
        
        // Si no envían monto personalizado, usamos el del catálogo
        $finalAmount = $validated['amount'] ?? $bonus->amount;

        $employee = Employee::findOrFail($validated['employee_id']);
        
        $employee->bonuses()->attach($bonus->id, [
            'assigned_date' => $validated['assigned_date'],
            'amount' => $finalAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Bono asignado al empleado correctamente.');
    }
}