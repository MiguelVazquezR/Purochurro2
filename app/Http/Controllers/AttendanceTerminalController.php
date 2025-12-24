<?php

namespace App\Http\Controllers;

use App\Enums\IncidentType;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\WorkSchedule;
use App\Services\RekognitionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceTerminalController extends Controller
{
    protected $rekognition;

    public function __construct(RekognitionService $rekognition)
    {
        $this->rekognition = $rekognition;
    }

    /**
     * [WEB] Obtiene el estado de asistencia del día para el empleado logueado.
     * Ruta: GET /attendance/status (name: attendance.status)
     */
    public function status(Request $request)
    {
        $user = auth()->user();
        // Buscar empleado asociado al usuario logueado
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee) {
            return response()->json(['status' => 'error', 'message' => 'Usuario no vinculado a un empleado.']);
        }

        $attendance = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', Carbon::today())
            ->first();

        // Determinar estado para la UI
        $state = 'none'; // Sin registro hoy
        if ($attendance) {
            if ($attendance->check_in && !$attendance->check_out) {
                $state = 'checked_in'; // Ya entró, falta salir
            } elseif ($attendance->check_in && $attendance->check_out) {
                $state = 'completed'; // Jornada terminada
            }
        }

        return response()->json([
            'status' => 'success',
            'state' => $state,
            'check_in' => $attendance?->check_in ? Carbon::parse($attendance->check_in)->format('h:i A') : null,
            'check_out' => $attendance?->check_out ? Carbon::parse($attendance->check_out)->format('h:i A') : null,
        ]);
    }

    /**
     * [WEB] Registra asistencia para el usuario logueado con validación facial.
     * Ruta: POST /attendance/web (name: attendance.web)
     */
    public function registerWeb(Request $request)
    {
        $request->validate([
            'image' => 'required|string', // Base64
        ]);

        $user = auth()->user();
        $employee = Employee::where('user_id', $user->id)->first();

        if (!$employee || !$employee->aws_face_id) {
            return response()->json([
                'status' => 'error', 
                'message' => 'No tienes perfil biométrico configurado.'
            ], 403);
        }

        // 1. Decodificar imagen
        $imageParts = explode(";base64,", $request->image);
        $imageBytes = base64_decode(end($imageParts));

        // 2. Buscar en AWS
        $match = $this->rekognition->searchFace($imageBytes);

        if (!$match) {
            return response()->json([
                'status' => 'error', 
                'message' => 'No se detectó ningún rostro válido.'
            ], 422);
        }

        // 3. SEGURIDAD: Verificar que el rostro detectado sea el del usuario logueado
        if ($match['face_id'] !== $employee->aws_face_id) {
             return response()->json([
                 'status' => 'error', 
                 'message' => 'Validación biométrica fallida: El rostro no coincide con tu perfil.'
             ], 403);
        }

        // 4. Reutilizar la lógica de registro existente
        return $this->processAttendance($employee, $request->image);
    }

    /**
     * [TERMINAL] Endpoint público/kiosco: Identifica cualquier empleado por su rostro.
     */
    public function register(Request $request)
    {
        $request->validate(['image' => 'required|string']);

        $imageParts = explode(";base64,", $request->image);
        $imageBytes = base64_decode(end($imageParts));

        $match = $this->rekognition->searchFace($imageBytes);

        if (!$match) {
            return response()->json(['status' => 'error', 'message' => 'Rostro no reconocido.'], 404);
        }

        $employee = Employee::where('aws_face_id', $match['face_id'])
            ->where('is_active', true)
            ->first();

        if (!$employee) {
            return response()->json(['status' => 'error', 'message' => 'Empleado no activo.'], 404);
        }

        return $this->processAttendance($employee, $request->image);
    }

    /**
     * Lógica centralizada de registro de asistencia
     */
    private function processAttendance(Employee $employee, string $base64Image)
    {
        $now = Carbon::now();
        $today = $now->format('Y-m-d');
        $time = $now->format('H:i:s');

        DB::beginTransaction();
        try {
            $attendance = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $today)
                ->first();

            $type = '';
            $message = '';

            // --- CHECK-IN ---
            if (!$attendance) {
                // Calcular retardo
                $schedule = WorkSchedule::with('shift')
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', $today)
                    ->first();

                $isLate = false;
                if ($schedule && $schedule->shift) {
                    // FIX: Double date specification error
                    // Extraemos SOLO la hora del turno para evitar concatenar dos fechas (2025-12-24 2025-12-24 10:00:00)
                    $shiftTimeStr = Carbon::parse($schedule->shift->start_time)->format('H:i:s');
                    
                    $entryLimit = Carbon::parse($today . ' ' . $shiftTimeStr);
                    if ($now->greaterThan($entryLimit)) {
                        $isLate = true;
                    }
                }

                $attendance = Attendance::create([
                    'employee_id' => $employee->id,
                    'date' => $today,
                    'check_in' => $time,
                    'incident_type' => IncidentType::ASISTENCIA,
                    'is_late' => $isLate,
                ]);

                $attendance->addMediaFromBase64($base64Image)
                    ->usingFileName("checkin_{$employee->id}_{$today}.jpg")
                    ->toMediaCollection('check_in_photo');

                $type = 'entrada';
                $message = "Bienvenido, {$employee->first_name}. " . ($isLate ? "(Retardo)" : "");
            } 
            // --- CHECK-OUT ---
            elseif ($attendance->check_out === null) {
                // Evitar doble check inmediato (5 min)
                // FIX: Double date specification error
                // Parseamos explícitamente solo la hora del check_in para asegurarnos
                $checkInTimeOnly = Carbon::parse($attendance->check_in)->format('H:i:s');
                $checkInDateTime = Carbon::parse($attendance->date->format('Y-m-d') . ' ' . $checkInTimeOnly);
                
                if ($checkInDateTime->diffInMinutes($now) < 1) {
                    return response()->json([
                        'status' => 'warning',
                        'message' => 'Entrada muy reciente. Espera para registrar salida.'
                    ]);
                }

                $attendance->update(['check_out' => $time]);

                $attendance->addMediaFromBase64($base64Image)
                    ->usingFileName("checkout_{$employee->id}_{$today}.jpg")
                    ->toMediaCollection('check_out_photo');

                $type = 'salida';
                $message = "Hasta luego, {$employee->first_name}.";
            } 
            // --- YA TERMINÓ ---
            else {
                return response()->json([
                    'status' => 'info',
                    'message' => "{$employee->first_name}, ya registraste salida hoy."
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'type' => $type,
                'employee' => $employee->full_name,
                'time' => $now->format('h:i A'),
                'message' => $message
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Attendance Error: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Error interno.'], 500);
        }
    }
}