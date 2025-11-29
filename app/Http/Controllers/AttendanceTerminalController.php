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
     * Endpoint principal: Recibe foto, identifica y registra.
     */
    public function register(Request $request)
    {
        $request->validate([
            'image' => 'required|string', // Base64 image
        ]);

        // 1. Decodificar imagen
        // Asumimos formato "data:image/jpeg;base64,..."
        $imageParts = explode(";base64,", $request->image);
        $imageBytes = base64_decode(end($imageParts));

        // 2. Buscar en AWS Rekognition
        $match = $this->rekognition->searchFace($imageBytes);

        if (!$match) {
            return response()->json([
                'status' => 'error',
                'message' => 'Rostro no reconocido. Intente nuevamente.'
            ], 404);
        }

        // 3. Buscar Empleado
        $employee = Employee::where('aws_face_id', $match['face_id'])
            ->where('is_active', true)
            ->first();

        if (!$employee) {
            // Caso raro: Existe en AWS pero no en DB local (quizás soft deleted)
            return response()->json([
                'status' => 'error',
                'message' => 'Identidad válida pero empleado no activo.'
            ], 404);
        }

        // 4. Lógica de Asistencia
        return $this->processAttendance($employee, $request->image);
    }

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

            // --- Lógica: CHECK-IN ---
            if (!$attendance) {
                // Buscamos horario para calcular retardos
                $schedule = WorkSchedule::with('shift')
                    ->where('employee_id', $employee->id)
                    ->whereDate('date', $today)
                    ->first();

                $isLate = false;
                if ($schedule && $schedule->shift) {
                    // Damos 15 mins de tolerancia (ajustable)
                    $entryLimit = Carbon::parse($today . ' ' . $schedule->shift->start_time)->addMinutes(15);
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

                // Guardar Foto Entrada
                $attendance->addMediaFromBase64($base64Image)
                    ->usingFileName("checkin_{$employee->id}_{$today}.jpg")
                    ->toMediaCollection('check_in_photo');

                $type = 'entrada';
                $message = "Bienvenido, {$employee->first_name}. " . ($isLate ? "(Retardo registrado)" : "");
            } 
            // --- Lógica: CHECK-OUT ---
            elseif ($attendance->check_out === null) {
                // Validación simple: Evitar doble checada inmediata (ej. en menos de 5 min)
                $checkInTime = Carbon::parse($attendance->date->format('Y-m-d') . ' ' . $attendance->check_in);
                if ($now->diffInMinutes($checkInTime) < 5) {
                    return response()->json([
                        'status' => 'warning',
                        'message' => 'Entrada registrada hace poco. Espere para registrar salida.'
                    ]);
                }

                $attendance->update(['check_out' => $time]);

                // Guardar Foto Salida
                $attendance->addMediaFromBase64($base64Image)
                    ->usingFileName("checkout_{$employee->id}_{$today}.jpg")
                    ->toMediaCollection('check_out_photo');

                $type = 'salida';
                $message = "Hasta luego, {$employee->first_name}. Turno finalizado.";
            } 
            // --- Lógica: YA CHECÓ SALIDA ---
            else {
                return response()->json([
                    'status' => 'info',
                    'message' => "{$employee->first_name}, ya registraste tu salida hoy."
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
            return response()->json(['status' => 'error', 'message' => 'Error interno al guardar asistencia.'], 500);
        }
    }
}