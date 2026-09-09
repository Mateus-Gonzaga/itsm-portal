<?php

namespace App\Console\Commands;

use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

class GoogleCalendarSync extends Command
{
    protected $signature = 'agenda:google-sync';

    protected $description = 'Puxa mudanças do Google Calendar (calendário da equipe) para a agenda do portal';

    public function handle(GoogleCalendarService $svc): int
    {
        if (! $svc->enabled()) {
            $this->info('Sincronização com o Google Calendar está desligada (config).');

            return self::SUCCESS;
        }

        $n = $svc->pull();
        $this->info("Sincronização concluída: {$n} evento(s) aplicado(s) do Google.");

        return self::SUCCESS;
    }
}
