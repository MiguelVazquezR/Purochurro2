<?php

use App\Http\Controllers\CategoryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Categorías
|--------------------------------------------------------------------------
*/

Route::resource('categories', CategoryController::class)
    ->except(['create', 'edit']); // Usaremos modales en el index