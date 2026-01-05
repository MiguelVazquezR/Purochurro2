<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB; // <-- Importante para la transacción

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
        $sortField = $request->input('sortField', 'date');
        $sortOrder = $request->input('sortOrder', 'desc'); 
        
        $allowedSorts = ['date', 'concept', 'amount', 'created_at'];
        if (in_array($sortField, $allowedSorts)) {
             $query->orderBy($sortField, $sortOrder);
        } else {
             $query->orderBy('date', 'desc');
        }

        $query->orderBy('created_at', 'desc');

        $expenses = $query->paginate(10)->withQueryString();

        return Inertia::render('Expense/Index', [
            'expenses' => $expenses,
            'filters' => $request->only(['search', 'sortField', 'sortOrder'])
        ]);
    }

    public function create()
    {
        return Inertia::render('Expense/Create');
    }

    // --- STORE ACTUALIZADO PARA MÚLTIPLES REGISTROS ---
    public function store(Request $request)
    {
        // Validamos un array de objetos 'items'
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.concept' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.date' => 'required|date',
            'items.*.notes' => 'nullable|string|max:1000',
        ], [
            'items.required' => 'Debes registrar al menos un gasto.',
            'items.*.concept.required' => 'El concepto es obligatorio en todas las líneas.',
            'items.*.amount.required' => 'El monto es obligatorio en todas las líneas.',
        ]);

        try {
            DB::transaction(function () use ($request, $validated) {
                foreach ($validated['items'] as $item) {
                    $request->user()->expenses()->create([
                        'concept' => $item['concept'],
                        'amount' => $item['amount'],
                        'date' => $item['date'],
                        'notes' => $item['notes'] ?? null,
                    ]);
                }
            });

            $count = count($validated['items']);
            return redirect()->route('expenses.index')
                ->with('success', "Se han registrado {$count} gastos correctamente.");

        } catch (\Exception $e) {
            return back()->with('error', 'Ocurrió un error al guardar los gastos: ' . $e->getMessage());
        }
    }

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