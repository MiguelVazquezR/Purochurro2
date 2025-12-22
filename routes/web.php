<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Rutas principales de la aplicación.
|
*/

// Redireccionamiento de la raíz '/' al login, ya que no hay Landing Page.
Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
])->group(function () {
    
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // --- Carga de Rutas Modulares ---
    
    // Rutas de Empleados
    require __DIR__ . '/web/employees.php';
    require __DIR__ . '/web/payrolls.php';
    require __DIR__ . '/web/holidays.php';
    require __DIR__ . '/web/bonuses.php';
    require __DIR__ . '/web/incident-requests.php';
    require __DIR__ . '/web/attendances.php';
    require __DIR__ . '/web/pos.php';
    require __DIR__ . '/web/products.php';
    require __DIR__ . '/web/categories.php';
    require __DIR__ . '/web/expenses.php';
    require __DIR__ . '/web/sales.php';
    require __DIR__ . '/web/reports.php';
    require __DIR__ . '/web/tutorials.php';

});