<?php

use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    // Rutas existentes...
    Route::post('/employees/{employee}/terminate', [EmployeeController::class, 'terminate'])->name('employees.terminate');
    Route::get('/employees/{employee}/contract/{type}', [EmployeeController::class, 'contract'])->name('employees.contract');
    Route::get('/employees/{employee}/acta', [EmployeeController::class, 'acta'])->name('employees.acta');
    Route::get('/employees/{employee}/resignation', [EmployeeController::class, 'resignation'])->name('employees.resignation');
    Route::get('/employees/{employee}/settlement', [EmployeeController::class, 'settlement'])->name('employees.settlement');

    // Carta de Recomendación
    Route::get('/employees/{employee}/recommendation', [EmployeeController::class, 'recommendation'])->name('employees.recommendation');

    // Rutas CRUD estándar
    Route::resource('employees', EmployeeController::class);
});