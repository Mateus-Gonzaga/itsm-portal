<?php

namespace App\Console\Commands;

use App\Services\GoogleCalendarService;
use Illuminate\Console\Command;

class GoogleCalendarSync extends Command
{
    protected $signature = 'agenda:google-sync';

    protected $description = 'Sincroniza tarefas do portal com o Google Calendar (envio e recebimento)';

    public function handle(GoogleCalendarService $svc): int
    {
        if (! $svc->enabled()) {
            $this->warn('Sincronização com o Google Calendar está desligada (config ou chave ausente).');

            return self::SUCCESS;
        }

        try {
            $pushed = $svc->pushAllPending();
            $this->info("Envio concluído: {$pushed} agendamento(s) enviados ao Google Calendar.");
        } catch (\Throwable $e) {
            $this->error("Falha ao enviar agendamentos ao Google: {$e->getMessage()}");
        }

        try {
            $pulled = $svc->pull();
            $this->info("Recebimento concluído: {$pulled} evento(s) recebidos do Google Calendar.");
        } catch (\Throwable $e) {
            $this->error("Falha ao puxar eventos do Google: {$e->getMessage()}");
        }

        return self::SUCCESS;
    }
}
