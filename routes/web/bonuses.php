<?php

use App\Http\Controllers\BonusController;
use Illuminate\Support\Facades\Route;

Route::resource('bonuses', BonusController::class)->except(['create', 'edit', 'show']);
Route::post('/bonuses/assign', [BonusController::class, 'assign'])->name('bonuses.assign');