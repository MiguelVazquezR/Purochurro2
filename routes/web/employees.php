<?php

use App\Http\Controllers\EmployeeController;
use Illuminate\Support\Facades\Route;

// Rutas de Empleados
Route::resource('employees', EmployeeController::class);
