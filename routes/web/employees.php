<?php

use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Ruta para dar de baja
    Route::post('/employees/{employee}/terminate', [EmployeeController::class, 'terminate'])->name('employees.terminate');
    
    // Ruta para generar Contratos
    Route::get('/employees/{employee}/contract/{type}', [EmployeeController::class, 'contract'])->name('employees.contract');

    // NUEVA RUTA: Generar Acta Administrativa
    Route::get('/employees/{employee}/acta', [EmployeeController::class, 'acta'])->name('employees.acta');

    // Rutas CRUD estándar
    Route::resource('employees', EmployeeController::class);
});