<?php

use App\Http\Controllers\SaleController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Historial de Ventas
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/sales', [SaleController::class, 'index'])->name('sales.index');
    
    // Cambiamos el parámetro {sale} por {dailyOperation} para ver el detalle del día
    Route::get('/sales/{dailyOperation}', [SaleController::class, 'show'])->name('sales.show');
    
    // Nueva ruta para procesar el corte
    Route::post('/sales/{dailyOperation}/close', [SaleController::class, 'close'])->name('sales.close');
});