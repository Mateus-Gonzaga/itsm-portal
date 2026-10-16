<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Puxa mudanças do Google Calendar a cada 5 min (só age se a sync estiver ligada).
Schedule::command('agenda:google-sync')->everyFiveMinutes()->withoutOverlapping();

// "Batimento" do agendador: a página /saude acusa se ele parar de rodar.
Schedule::call(fn () => Cache::put('saude:scheduler', time(), 3600))
    ->everyMinute()->name('batimento-agendador');

// O cache das leituras do GLPI usa versões por grupo: as chaves antigas expiram
// mas ficam na tabela "cache". Limpa as vencidas de hora em hora.
Schedule::call(function () {
    if (config('cache.default') === 'database') {
        DB::table(config('cache.stores.database.table', 'cache'))
            ->where('expiration', '<', time())
            ->delete();
    }
})->hourly()->name('limpar-cache-vencido')->withoutOverlapping();
