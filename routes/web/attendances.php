<?php

use App\Http\Controllers\AttendanceTerminalController;
use Illuminate\Support\Facades\Route;

Route::post('/attendance/terminal', [AttendanceTerminalController::class, 'register'])
    ->name('attendance.terminal');