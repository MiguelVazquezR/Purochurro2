<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ExpenseController extends Controller
{
    public function index(Request $request)
    {
        $query = Expense::with('user');

        // --- Búsqueda Global (Server-Side) ---
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('concept', 'like', "%{$search}%")
                  ->orWhere('amount', 'like', "%{$search}%")
                  ->orWhereHas('user', function($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%");
                  });
            });
        }

        // --- Ordenamiento ---
        // Manejamos el ordenamiento dinámico o por defecto
        $sortField = $request->input('sortField', 'date');
        $sortOrder = $request->input('sortOrder', 'desc'); // 'asc' o 'desc'
        
        // Mapeo simple para asegurar que solo ordenamos por columnas permitidas
        $allowedSorts = ['date', 'concept', 'amount', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
             $query->orderBy($sortField, $sortOrder);
        } else {
             $query->orderBy('date', 'desc');
        }

        // Siempre un orden secundario para consistencia en la paginación
        $query->orderBy('created_at', 'desc');

        $expenses = $query->paginate(10)->withQueryString();

        return Inertia::render('Expense/Index', [
            'expenses' => $expenses,
            'filters' => $request->only(['search', 'sortField', 'sortOrder'])
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