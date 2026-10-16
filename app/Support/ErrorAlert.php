<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa a equipe por e-mail quando o portal tem um erro inesperado (500).
 *
 * - Só erros que o Laravel já reportaria (404, validação, login etc. ficam de fora).
 * - O mesmo erro (classe + arquivo + linha) é avisado no máximo 1x a cada 30 min,
 *   e no total no máximo 20 avisos por hora — uma pane não lota a caixa de e-mail.
 * - O envio acontece depois da resposta e nunca derruba a página.
 */
class ErrorAlert
{
    private const SAME_ERROR_TTL = 1800;

    private const MAX_PER_HOUR = 20;

    private static bool $sending = false;

    public static function report(Throwable $e): void
    {
        $to = self::recipient();
        if ($to === null || self::$sending || app()->runningUnitTests()) {
            return;
        }

        try {
            $fingerprint = sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
            if (! Cache::add('error-alert:'.$fingerprint, 1, self::SAME_ERROR_TTL)) {
                return; // já avisado recentemente
            }
            $hourKey = 'error-alert:count:'.now()->format('YmdH');
            Cache::add($hourKey, 0, 3700);
            if (Cache::increment($hourKey) > self::MAX_PER_HOUR) {
                return;
            }
        } catch (Throwable) {
            return; // sem cache disponível, não arrisca inundar
        }

        $context = self::context($e);
        app()->terminating(function () use ($to, $context) {
            self::$sending = true;
            try {
                Mail::raw($context['body'], function ($m) use ($to, $context) {
                    $m->to($to)->subject($context['subject']);
                });
            } catch (Throwable) {
                // falha no e-mail não pode gerar outro aviso
            } finally {
                self::$sending = false;
            }
        });
    }

    private static function recipient(): ?string
    {
        $to = trim((string) config('portal.error_alert_email', ''));
        if (strtolower($to) === 'off') {
            return null;
        }
        $to = $to !== '' ? $to : (string) config('mail.from.address');

        return filter_var($to, FILTER_VALIDATE_EMAIL) ? $to : null;
    }

    /** @return array{subject:string, body:string} */
    private static function context(Throwable $e): array
    {
        $request = app()->runningInConsole() ? null : request();
        $user = $request?->user();
        $file = str_replace(base_path().DIRECTORY_SEPARATOR, '', $e->getFile());
        $trace = collect(explode("\n", $e->getTraceAsString()))->take(12)->implode("\n");

        $lines = [
            'O portal FOURLINE Connect registrou um erro.',
            '',
            'Quando:   '.now()->format('d/m/Y H:i:s'),
            'Erro:     '.get_class($e),
            'Mensagem: '.mb_strimwidth($e->getMessage(), 0, 500, '…'),
            'Local:    '.$file.':'.$e->getLine(),
        ];
        if ($request) {
            $lines[] = 'Página:   '.$request->method().' '.$request->fullUrl();
            $lines[] = 'Usuário:  '.($user ? $user->name.' (#'.$user->id.', '.$user->role?->value.')' : 'não logado');
            $lines[] = 'IP:       '.$request->ip();
        } else {
            $lines[] = 'Origem:   comando/agendamento (console)';
        }
        $lines[] = '';
        $lines[] = 'Rastreamento (resumo):';
        $lines[] = $trace;
        $lines[] = '';
        $lines[] = 'Detalhes completos em storage/logs/laravel.log. Este mesmo erro não será avisado de novo nos próximos 30 minutos.';

        return [
            'subject' => '[Portal FOURLINE] Erro: '.mb_strimwidth(class_basename($e).' — '.$e->getMessage(), 0, 120, '…'),
            'body' => implode("\n", $lines),
        ];
    }
}
