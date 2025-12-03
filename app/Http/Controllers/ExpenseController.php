<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index()
    {
        $expenses = Expense::with('user')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Expense/Index', [
            'expenses' => $expenses
        ]);
    }

    // --- NUEVO MÉTODO AGREGADO ---
    public function create()
    {
        return Inertia::render('Expense/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'concept' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $request->user()->expenses()->create($validated);

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto registrado correctamente.');
    }

    // Método edit opcional si planeas hacer una página de edición dedicada también
    public function edit(Expense $expense)
    {
        return Inertia::render('Expense/Edit', [
            'expense' => $expense
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $validated = $request->validate([
            'concept' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $expense->update($validated);

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto actualizado correctamente.');
    }

    public function destroy(Expense $expense)
    {
        $expense->delete();

        return redirect()->route('expenses.index')
            ->with('success', 'Gasto eliminado correctamente.');
    }
}