<?php

use App\Http\Controllers\IncidentRequestController;
use Illuminate\Support\Facades\Route;

// Solicitudes de Incidencias (Requests)
Route::resource('incident-requests', IncidentRequestController::class)
    ->only(['index', 'store']); // Index y Store son suficientes

// Ruta personalizada para aprobar/rechazar (Patch)
Route::patch('/incident-requests/{incidentRequest}/status', [IncidentRequestController::class, 'updateStatus'])
    ->name('incident-requests.update-status');