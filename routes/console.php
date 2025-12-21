<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Console\Commands\AccrueVacationDays;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// --- TAREAS PROGRAMADAS ---

// Ejecutar la acumulación de vacaciones cada Domingo a las 00:00 hrs.
Schedule::command(AccrueVacationDays::class)->weeklyOn(0, '00:00');