<?php

use App\Http\Controllers\PayrollController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\ShiftController;
use Illuminate\Support\Facades\Route;

// Payroll
Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
Route::get('/payroll/week/{startDate}', [PayrollController::class, 'week'])->name('payroll.week');
Route::post('/payroll/update-day', [PayrollController::class, 'updateDay'])->name('payroll.update-day');
// --- NUEVA RUTA PARA ELIMINAR ASISTENCIA ---
Route::delete('/payroll/day/{id}', [PayrollController::class, 'deleteDay'])->name('payroll.delete-day');
Route::get('/payroll/settlement/{startDate}', [PayrollController::class, 'settlement'])->name('payroll.settlement');

// RUTA: Acción de cierre
Route::post('/payroll/settlement', [PayrollController::class, 'storeSettlement'])->name('payroll.store-settlement');
Route::get('/payroll/receipts/{startDate}', [PayrollController::class, 'receipts'])->name('payroll.receipts');

// Rutas de Turnos (Catálogo)
// Usamos apiResource o resource excluyendo create/edit/show si usamos modales en el index
Route::resource('shifts', ShiftController::class)->except(['show']);

// Rutas de Horarios / Calendario
Route::get('/schedule', [ScheduleController::class, 'index'])->name('schedule.index');
Route::post('/schedule/day', [ScheduleController::class, 'store'])->name('schedule.store');
Route::post('/schedule/generate', [ScheduleController::class, 'generateWeek'])->name('schedule.generate');