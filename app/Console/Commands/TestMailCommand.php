<?php

namespace App\Console\Commands;

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

        $this->info('Tentando enviar email de teste via SMTP...');

        try {
            Mail::raw('Este e um email de teste enviado pelo Portal FOURLINE Connect via SMTP ('.now()->format('d/m/Y H:i:s').').', function ($msg) use ($to) {
                $msg->to($to)
                    ->subject('[Fourline Portal] Teste de Envio SMTP');
            });

            $this->info("SUCESSO: Email de teste enviado com exito para: {$to}!");
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
