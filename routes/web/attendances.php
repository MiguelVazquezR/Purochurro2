<?php

use App\Http\Controllers\AttendanceTerminalController;
use Illuminate\Support\Facades\Route;

// 1. Ruta Pública para Terminal Física (Kiosco)
// Esta ruta suele usarse sin sesión de usuario, autenticando solo por token de dispositivo o IP si es necesario
Route::post('/attendance/terminal', [AttendanceTerminalController::class, 'register'])
    ->name('attendance.terminal');

// 2. Rutas Web para el Usuario Logueado
// Estas rutas requieren que el empleado haya iniciado sesión en el sistema
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    // Obtener estado actual (Entrada, Salida, etc.) para el botón del Topbar
    Route::get('/attendance/status', [AttendanceTerminalController::class, 'status'])
        ->name('attendance.status');

    // Registrar asistencia desde la webcam del navegador
    Route::post('/attendance/web', [AttendanceTerminalController::class, 'registerWeb'])
        ->name('attendance.web');
});