<?php

namespace App\Console\Commands;

use App\Data\TicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Mail\TicketCreatedMail;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestMailCommand extends Command
{
    protected $signature = 'mail:test {to? : Destinatario do email de teste}';

    protected $description = 'Testa a configuracao de envio de emails via SMTP do portal';

    public function handle(): int
    {
        $to = $this->argument('to') ?: config('mail.from.address');

        $this->info('--- Diagnosticando Configuracao de Email ---');
        $this->line('Mailer: '.config('mail.default'));
        $this->line('Host: '.config('mail.mailers.smtp.host'));
        $this->line('Porta: '.config('mail.mailers.smtp.port'));
        $this->line('Usuario: '.config('mail.mailers.smtp.username'));
        $this->line('Senha configurada: '.(config('mail.mailers.smtp.password') ? 'SIM' : 'NAO'));
        $this->line('From: '.config('mail.from.address').' ('.config('mail.from.name').')');
        $this->line('Destinatario: '.$to);
        $this->newLine();

        if (config('mail.default') === 'log') {
            $this->warn('AVISO: MAIL_MAILER esta como "log"! Os emails nao sao enviados, apenas gravados em storage/logs/laravel.log.');
        }

        $this->info("1. Disparando o template oficial de chamado (TicketCreatedMail) para: {$to}...");

        try {
            $dummyTicket = new TicketData(
                id: 9999,
                title: 'Teste de Envio de Chamado',
                description: 'Este e um chamado de teste para validar o envio de emails formatados com a identidade visual da Fourline.',
                status: TicketStatus::New,
                priority: TicketPriority::High,
                type: TicketType::Incident,
                requesterName: 'Equipe Fourline',
                entity: 'Fourline Suporte TI',
                createdAt: CarbonImmutable::now(),
                category: 'Hardware',
                dueDate: CarbonImmutable::now()->addHours(4),
            );

            Mail::to($to)->send(new TicketCreatedMail(
                ticket: $dummyTicket,
                ticketUrl: url('/tickets/9999'),
                isStaffNotification: false
            ));

            $this->info("SUCESSO: Email com layout HTML enviado com exito para {$to}!");
            $this->line('Verifique agora a caixa de entrada (e a pasta de Spam/Lixo Eletronico).');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('FALHA AO ENVIAR EMAIL:');
            $this->error($e->getMessage());
            $this->newLine();
            $this->line('Tipo da excecao: '.get_class($e));
            return self::FAILURE;
        }
    }
}
