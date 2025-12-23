<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HolidayController extends Controller
{
    public function index()
    {
        return Inertia::render('Holiday/Index', [
            'holidays' => Holiday::orderBy('date', 'desc')->get()
        ]);
    }

    // --- NUEVO ---
    public function create()
    {
        return Inertia::render('Holiday/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'date' => 'required|date|unique:holidays,date',
            'pay_multiplier' => 'required|numeric|min:1|max:5', // Ej: 2.0, 3.0
            'mandatory_rest' => 'boolean'
        ]);

        Holiday::create($validated);

        return redirect()->route('holidays.index')
            ->with('success', 'Día feriado registrado.');
    }

    // --- NUEVO ---
    public function edit(Holiday $holiday)
    {
        return Inertia::render('Holiday/Edit', [
            'holiday' => $holiday
        ]);
    }

    public function update(Request $request, Holiday $holiday)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'date' => 'required|date|unique:holidays,date,' . $holiday->id,
            'pay_multiplier' => 'required|numeric',
            'mandatory_rest' => 'boolean'
        ]);

        $holiday->update($validated);
        
        return redirect()->route('holidays.index')
            ->with('success', 'Día feriado actualizado.');
    }

    public function destroy(Holiday $holiday)
    {
        $holiday->delete();
        return redirect()->route('holidays.index')
            ->with('success', 'Día feriado eliminado.');
    }
}