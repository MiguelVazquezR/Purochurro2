<?php

use App\Http\Controllers\HolidayController;
use Illuminate\Support\Facades\Route;

Route::resource('holidays', HolidayController::class)->except(['create', 'edit', 'show']);