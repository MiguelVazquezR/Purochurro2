<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas de Gestión de Productos
|--------------------------------------------------------------------------
|
| Aquí se registran las rutas para el CRUD de productos.
| Se asume que estas rutas están protegidas por middleware de autenticación
| en el archivo donde se incluyen (web.php).
|
*/

Route::resource('products', ProductController::class);