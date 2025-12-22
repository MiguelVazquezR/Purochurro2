<?php

use App\Http\Controllers\TutorialController;
use Illuminate\Support\Facades\Route;

Route::post('/tutorials/complete', [TutorialController::class, 'complete'])->name('tutorials.complete');
Route::get('/tutorials/check/{moduleName}', [TutorialController::class, 'check'])->name('tutorials.check');