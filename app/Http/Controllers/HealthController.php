<?php

namespace App\Http\Controllers;

use App\Repositories\Glpi\Support\GlpiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Página de saúde para o Zabbix (GET /saude?token=...).
 *
 * Responde sempre JSON com 1/0 e números (fácil de ler no Zabbix):
 *   db, cache, glpi                → 1 ok / 0 falhou
 *   glpi_fila_pendentes            → e-mails do GLPI aguardando envio (-1 = desconhecido)
 *   glpi_fila_atraso_min           → idade (min) do e-mail mais antigo na fila (-1 = desconhecido, 0 = vazia)
 *   glpi_fila_cron_ativa           → 1 ação automática de envio ativa / 0 desativada / -1 desconhecido
 *   scheduler_atraso_min           → minutos desde o último "batimento" do agendador
 *   disco_livre_pct                → % livre no disco do portal
 *
 * Protegida por token (PORTAL_HEALTH_TOKEN); sem token configurado → 404.
 */
class HealthController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $expected = (string) config('portal.health_token', '');
        abort_if($expected === '' || ! hash_equals($expected, (string) $request->query('token', '')), 404);

        $out = [
            'db' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                // Texto (não número): o Redis devolve números como string.
                $marca = 'ok-'.now()->timestamp;
                Cache::put('saude:ping', $marca, 60);

                return Cache::get('saude:ping') === $marca ?: throw new \RuntimeException('cache');
            }),
            'glpi' => 0,
            'glpi_fila_pendentes' => -1,
            'glpi_fila_atraso_min' => -1,
            'glpi_fila_cron_ativa' => -1,
            'scheduler_atraso_min' => $this->schedulerDelay(),
            'disco_livre_pct' => $this->diskFreePct(),
        ];

        if (config('glpi.driver') === 'api') {
            $this->glpiChecks($out);
        } else {
            $out['glpi'] = 1; // modo demonstração
        }

        $out['ok'] = (int) ($out['db'] && $out['cache'] && $out['glpi']);
        $out['verificado_em'] = now()->toIso8601String();

        return response()->json($out)->header('Cache-Control', 'no-store');
    }

    private function check(callable $fn): int
    {
        try {
            $fn();

            return 1;
        } catch (\Throwable) {
            return 0;
        }
    }

    private function glpiChecks(array &$out): void
    {
        $url = (string) config('glpi.api.url');
        $appToken = (string) config('glpi.api.app_token');

        try {
            $token = GlpiCache::serviceToken($url, (string) config('glpi.api.user'), (string) config('glpi.api.password'), $appToken);
            $client = GlpiCache::client($url, $appToken, $token,
                fn () => GlpiCache::serviceToken($url, (string) config('glpi.api.user'), (string) config('glpi.api.password'), $appToken, fresh: true));

            $profiles = $client->get('/getMyProfiles');
            $out['glpi'] = $profiles->successful() ? 1 : 0;
            if (! $out['glpi']) {
                return;
            }

            // Fila de e-mails: total pela Content-Range e o mais antigo (menor id).
            $q = (clone $client)->get('/QueuedNotification', ['range' => '0-0', 'sort' => 'id', 'order' => 'ASC']);
            if ($q->successful()) {
                $total = preg_match('~/(\d+)$~', (string) $q->header('Content-Range'), $m) ? (int) $m[1] : count((array) $q->json());
                $out['glpi_fila_pendentes'] = $total;
                $first = ((array) $q->json())[0] ?? null;
                $out['glpi_fila_atraso_min'] = ($total > 0 && ! empty($first['create_time']))
                    ? (int) max(0, now()->diffInMinutes(\Carbon\CarbonImmutable::parse($first['create_time'], config('glpi.timezone', 'UTC')), true))
                    : 0;
            }

            // Ação automática "queuednotification": state 0 = desativada.
            $c = (clone $client)->get('/CronTask', ['searchText[name]' => 'queuednotification', 'range' => '0-20']);
            if ($c->successful()) {
                $task = collect((array) $c->json())->first(fn ($t) => ($t['name'] ?? '') === 'queuednotification');
                if ($task) {
                    $out['glpi_fila_cron_ativa'] = (int) ($task['state'] ?? 0) === 0 ? 0 : 1;
                }
            }
        } catch (\Throwable) {
            $out['glpi'] = 0;
        }
    }

    private function schedulerDelay(): int
    {
        try {
            $last = Cache::get('saude:scheduler');

            return $last ? (int) max(0, floor((time() - (int) $last) / 60)) : 9999;
        } catch (\Throwable) {
            return 9999;
        }
    }

    private function diskFreePct(): float
    {
        $path = storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        return ($total && $free !== false) ? round($free / $total * 100, 1) : -1;
    }
}
