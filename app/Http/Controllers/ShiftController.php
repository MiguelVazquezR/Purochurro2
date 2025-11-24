<?php

namespace App\Http\Controllers;

use App\Models\Shift;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ShiftController extends Controller
{
    /**
     * Muestra la lista de turnos.
     * Para Catálogos pequeños como este, Index suele servir para Crear/Editar también (vía Modales).
     */
    public function index()
    {
        return Inertia::render('Shift/Index', [
            'shifts' => Shift::orderBy('start_time')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'color' => 'nullable|string|max:7',
        ]);

        Shift::create($validated);

        return redirect()->back()->with('success', 'Turno creado correctamente.');
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

        $shift->update($validated);

        return redirect()->back()->with('success', 'Turno actualizado.');
    }

    public function destroy(Shift $shift)
    {
        $shift->delete();
        return redirect()->back()->with('success', 'Turno eliminado.');
    }
}