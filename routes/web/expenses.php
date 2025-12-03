<?php

use App\Http\Controllers\ExpenseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Gastos
|--------------------------------------------------------------------------
*/

Route::resource('expenses', ExpenseController::class);