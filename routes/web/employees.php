<?php

use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftController; // Agregado
use Illuminate\Support\Facades\Route;

// Rutas de Empleados
Route::resource('employees', EmployeeController::class);

// Rutas de Turnos (Catálogo)
// Usamos apiResource o resource excluyendo create/edit/show si usamos modales en el index
Route::resource('shifts', ShiftController::class)->except(['create', 'edit', 'show']);

// Rutas de Horarios / Calendario
Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
Route::post('/schedule/day', [ScheduleController::class, 'store'])->name('schedule.store');
Route::post('/schedule/generate', [ScheduleController::class, 'generateWeek'])->name('schedule.generate');