<?php

use App\Http\Controllers\PosController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\StockAdjustmentController; // Agregado
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas POS e Inventario
|--------------------------------------------------------------------------
*/

// 1. Traspasos (Cocina <-> Carrito)
Route::resource('stock-transfers', StockTransferController::class)->only(['index', 'store']);

// 2. Ajustes (Entradas / Salidas / Mermas)
Route::resource('stock-adjustments', StockAdjustmentController::class)->only(['index', 'store']);

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/open', [PosController::class, 'openDay'])->name('pos.open');
    Route::post('/pos/sale', [PosController::class, 'storeSale'])->name('pos.store-sale');
    Route::post('/pos/close', [PosController::class, 'closeDay'])->name('pos.close');
});