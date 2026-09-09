<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Puxa mudanças do Google Calendar a cada 5 min (só age se a sync estiver ligada).
Schedule::command('agenda:google-sync')->everyFiveMinutes()->withoutOverlapping();
