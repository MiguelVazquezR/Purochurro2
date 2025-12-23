<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    public function index()
    {
        return Inertia::render('Shift/Index', [
            'shifts' => Shift::orderBy('start_time')->get()
        ]);
    }

    // --- NUEVO ---
    public function create()
    {
        return Inertia::render('Shift/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean',
        ]);

        $validated['color'] = '#' . $validated['color'];

        Shift::create($validated);

        return redirect()->route('shifts.index')
            ->with('success', 'Turno creado correctamente.');
    }

    // --- NUEVO ---
    public function edit(Shift $shift)
    {
        return Inertia::render('Shift/Edit', [
            'shift' => $shift
        ]);
    }

    public function update(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color' => 'nullable|string|max:7',
            'is_active' => 'boolean'
        ]);

        $validated['color'] = '#' . $validated['color'];

        $shift->update($validated);

        return redirect()->route('shifts.index')
            ->with('success', 'Turno actualizado.');
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();
        return redirect()->route('shifts.index')
            ->with('success', 'Turno eliminado.');
    }
}
