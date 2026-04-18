<?php

namespace App\Http\Controllers;

use App\Models\Logbook;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class LogbookController extends Controller
{
    /**
     * Muestra la lista de bitácoras (con navegación por semanas).
     */
    public function index(Request $request)
    {
        $offset = $request->integer('offset', 0); 
        
        $startOfWeek = now()->addWeeks($offset)->startOfWeek(Carbon::SUNDAY);
        $endOfWeek = now()->addWeeks($offset)->endOfWeek(Carbon::SATURDAY);

        $logbooks = Logbook::with(['author:id,name,profile_photo_path', 'readers:id,name', 'media'])
            ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
            ->oldest()
            ->get();

        return Inertia::render('Logbook/Index', [
            'logbooks' => $logbooks,
            'currentUserId' => auth()->id(),
            'weekStart' => $startOfWeek->translatedFormat('d M'),
            'weekEnd' => $endOfWeek->translatedFormat('d M, Y'),
            'weekOffset' => $offset,
        ]);
    }

    /**
     * Almacena una nueva bitácora y sus evidencias.
     */
    public function store(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
            'images' => 'nullable|array|max:5', 
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $logbook = Logbook::create([
                'user_id' => auth()->id(),
                'content' => $request->content,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $logbook->addMedia($image)->toMediaCollection('evidence');
                }
            }

            // El autor automáticamente la marca como leída
            $logbook->readers()->attach(auth()->id(), ['read_at' => now()]);

            DB::commit();

            return back()->with('success', 'Bitácora registrada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Ocurrió un error al guardar la bitácora: ' . $e->getMessage());
        }
    }

    /**
     * Muestra una bitácora en específico y la MARCA COMO LEÍDA en automático.
     */
    public function show(Logbook $logbook)
    {
        // Al abrir la bitácora, se marca como leída para el usuario autenticado
        $logbook->readers()->syncWithoutDetaching([
            auth()->id() => ['read_at' => now()]
        ]);

        $logbook->load(['author:id,name,profile_photo_path', 'readers:id,name', 'media']);

        // Dependiendo de tu frontend, podrías retornar una vista Show 
        // o retornar JSON si abres la información en un Modal lateral (Drawer/Dialog)
        if (request()->wantsJson()) {
            return response()->json($logbook);
        }

        return Inertia::render('Logbook/Show', [
            'logbook' => $logbook,
            'currentUserId' => auth()->id(),
        ]);
    }

    /**
     * Actualiza el contenido de una bitácora.
     */
    public function update(Request $request, Logbook $logbook)
    {
        // Seguridad: Solo el autor o el administrador (id 1) pueden editar
        if ($logbook->user_id !== auth()->id() && auth()->id() !== 1) {
            abort(403, 'No tienes permiso para editar esta bitácora.');
        }

        $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        $logbook->update([
            'content' => $request->content,
        ]);

        return back()->with('success', 'Bitácora actualizada correctamente.');
    }

    /**
     * Elimina una bitácora.
     */
    public function destroy(Logbook $logbook)
    {
        // Seguridad: Solo el autor o el administrador (id 1) pueden eliminar
        if ($logbook->user_id !== auth()->id() && auth()->id() !== 1) {
            abort(403, 'No tienes permiso para eliminar esta bitácora.');
        }

        $logbook->delete();

        return back()->with('success', 'Bitácora eliminada correctamente.');
    }

    /**
     * Marca una bitácora como leída (acción manual alternativa).
     */
    public function markAsRead(Logbook $logbook)
    {
        $logbook->readers()->syncWithoutDetaching([
            auth()->id() => ['read_at' => now()]
        ]);

        return back()->with('success', 'Marcado como enterado.');
    }
}