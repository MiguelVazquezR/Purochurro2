<?php

use App\Http\Controllers\PayrollController;
use Illuminate\Support\Facades\Route;

// Payroll
Route::get('/payroll', [PayrollController::class, 'index'])->name('payroll.index');
Route::get('/payroll/week/{startDate}', [PayrollController::class, 'week'])->name('payroll.week');
Route::post('/payroll/update-day', [PayrollController::class, 'updateDay'])->name('payroll.update-day');
Route::get('/payroll/settlement/{startDate}', [PayrollController::class, 'settlement'])->name('payroll.settlement');

// NUEVA RUTA: Acción de cierre
Route::post('/payroll/settlement', [PayrollController::class, 'storeSettlement'])->name('payroll.store-settlement');