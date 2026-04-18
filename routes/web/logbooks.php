<?php

use App\Http\Controllers\LogbookController;
use Illuminate\Support\Facades\Route;

// Ruta personalizada para lectura manual (debe ir antes del resource)
Route::post('/logbooks/{logbook}/read', [LogbookController::class, 'markAsRead'])->name('logbooks.mark-read');

// Rutas Resource (cubre index, create, store, show, edit, update, destroy)
Route::resource('logbooks', LogbookController::class);