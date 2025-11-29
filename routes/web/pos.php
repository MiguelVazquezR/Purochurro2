<?php

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