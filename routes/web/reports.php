<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ReportController;

/*
|--------------------------------------------------------------------------
| Rutas de Reportes
|--------------------------------------------------------------------------
|
| Este archivo se carga dentro de routes/web.php.
| Asegúrate de envolverlo en el middleware 'auth' y 'admin' si es necesario.
|
*/

Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');

// Futuras rutas sugeridas:
// Route::get('/export/pdf', [ReportController::class, 'exportPdf'])->name('reports.export.pdf');
// Route::get('/export/excel', [ReportController::class, 'exportExcel'])->name('reports.export.excel');