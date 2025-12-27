<?php

namespace App\Http\Controllers;

use App\Models\Bonus;
use App\Models\Employee;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Validation\Rule;

class BonusController extends Controller
{
    public function index()
    {
        return Inertia::render('Bonus/Index', [
            'bonuses' => Bonus::orderBy('name')->get(),
        ]);
    }

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
            // Validación de reglas (opcional, puede ser null)
            'rule_config' => 'nullable|array',
            // Si se envían reglas, validamos su estructura interna
            'rule_config.concept' => 'required_with:rule_config|string|in:late_minutes,unjustified_absences,extra_minutes,attendance',
            'rule_config.operator' => 'required_with:rule_config|string|in:<=,>=,=,>,<',
            'rule_config.value' => 'required_with:rule_config|numeric',
            'rule_config.scope' => 'required_with:rule_config|string|in:daily,period_total,period_accumulated',
            // AQUI AGREGAMOS 'per_day_worked'
            'rule_config.behavior' => 'required_with:rule_config|string|in:fixed_amount,pay_per_unit,per_day_worked',
        ]);

        Bonus::create($validated);

        return redirect()->route('bonuses.index')
            ->with('success', 'Bono creado correctamente.');
    }

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
            
            'rule_config' => 'nullable|array',
            'rule_config.concept' => 'required_with:rule_config|string|in:late_minutes,unjustified_absences,extra_minutes,attendance',
            'rule_config.operator' => 'required_with:rule_config|string|in:<=,>=,=,>,<',
            'rule_config.value' => 'required_with:rule_config|numeric',
            'rule_config.scope' => 'required_with:rule_config|string|in:daily,period_total,period_accumulated',
            // AQUI AGREGAMOS 'per_day_worked'
            'rule_config.behavior' => 'required_with:rule_config|string|in:fixed_amount,pay_per_unit,per_day_worked',
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