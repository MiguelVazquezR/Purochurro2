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
        // Mostramos todos (incluso inactivos) para gestión
        return Inertia::render('Bonus/Index', [
            'bonuses' => Bonus::orderBy('name')->get(),
        ]);
    }

    // --- NUEVO ---
    public function create()
    {
        return Inertia::render('Bonus/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'type' => 'required|string|in:fixed,percentage', 
            'is_active' => 'boolean',
        ]);

        Bonus::create($validated);

        return redirect()->route('bonuses.index')
            ->with('success', 'Bono creado correctamente.');
    }

    // --- NUEVO ---
    public function edit(Bonus $bonus)
    {
        return Inertia::render('Bonus/Edit', [
            'bonus' => $bonus
        ]);
    }

    public function update(Request $request, Bonus $bonus)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'type' => 'required|string|in:fixed,percentage',
            'is_active' => 'boolean',
        ]);

        $bonus->update($validated);
        
        return redirect()->route('bonuses.index')
            ->with('success', 'Bono actualizado.');
    }

    public function destroy(Bonus $bonus)
    {
        $bonus->delete();
        return redirect()->route('bonuses.index')
            ->with('success', 'Bono eliminado.');
    }

    /**
     * Asigna un bono a un empleado (Función API/Ajax, se mantiene igual o back)
     */
    public function assign(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'bonus_id' => 'required|exists:bonuses,id',
            'assigned_date' => 'required|date',
            'amount' => 'nullable|numeric|min:0', 
        ]);

        $bonus = Bonus::findOrFail($validated['bonus_id']);
        $finalAmount = $validated['amount'] ?? $bonus->amount;
        $employee = Employee::findOrFail($validated['employee_id']);
        
        $employee->bonuses()->attach($bonus->id, [
            'assigned_date' => $validated['assigned_date'],
            'amount' => $finalAmount,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', 'Bono asignado correctamente.');
    }
}