<?php

namespace App\Services;

use App\Models\AgendaTask;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sincronização com o Google Calendar (calendário único da equipe) via CONTA
 * DE SERVIÇO — sem OAuth por usuário e sem biblioteca externa (auth por JWT
 * assinado com openssl + chamadas REST). Tudo desligado por padrão (config).
 */
class GoogleCalendarService
{
    private const TOKEN_CACHE = 'gcal_access_token';
    private const SYNC_TOKEN = 'gcal_sync_token';
    private const BASE = 'https://www.googleapis.com/calendar/v3';

    /** Flag para evitar eco (o pull não deve reempurrar o que acabou de trazer). */
    public static bool $syncing = false;

    public function enabled(): bool
    {
        return (bool) config('googlecal.enabled')
            && filled(config('googlecal.calendar_id'))
            && is_file((string) config('googlecal.key_path'));
    }

    // ---------------------------------------------------------------- Push (portal → Google)

    public function pushCreate(AgendaTask $task): void
    {
        if (! $this->enabled() || self::$syncing) {
            return;
        }
        try {
            $resp = $this->api()->post($this->cal().'/events', $this->body($task));
            $id = $resp->json('id');
            if ($id) {
                $task->updateQuietly(['google_event_id' => $id]);
            }
        } catch (\Throwable $e) {
            Log::warning('GCal pushCreate falhou: '.$e->getMessage());
        }
    }

    public function pushUpdate(AgendaTask $task): void
    {
        if (! $this->enabled() || self::$syncing) {
            return;
        }
        if (empty($task->google_event_id)) {
            $this->pushCreate($task); // ainda não estava no Google
            return;
        }
        try {
            $this->api()->patch($this->cal().'/events/'.rawurlencode($task->google_event_id), $this->body($task));
        } catch (\Throwable $e) {
            Log::warning('GCal pushUpdate falhou: '.$e->getMessage());
        }
    }

    public function pushDelete(AgendaTask $task): void
    {
        if (! $this->enabled() || self::$syncing || empty($task->google_event_id)) {
            return;
        }
        try {
            $this->api()->delete($this->cal().'/events/'.rawurlencode($task->google_event_id));
        } catch (\Throwable $e) {
            Log::warning('GCal pushDelete falhou: '.$e->getMessage());
        }
    }

    // ---------------------------------------------------------------- Pull (Google → portal)

    /** Puxa mudanças feitas direto no Google e reflete nas tarefas do portal. */
    public function pull(): int
    {
        if (! $this->enabled()) {
            return 0;
        }

        self::$syncing = true;
        $changed = 0;
        try {
            $syncToken = Cache::get(self::SYNC_TOKEN);
            $pageToken = null;

            do {
                $params = ['showDeleted' => 'true', 'maxResults' => 250];
                if ($syncToken) {
                    $params['syncToken'] = $syncToken;
                } else {
                    $params['timeMin'] = now()->subMonths(2)->toRfc3339String();
                    $params['singleEvents'] = 'true';
                }
                if ($pageToken) {
                    $params['pageToken'] = $pageToken;
                }

                $resp = $this->api()->get($this->cal().'/events', $params);

                // syncToken expirado → refaz do zero.
                if ($resp->status() === 410) {
                    Cache::forget(self::SYNC_TOKEN);

                    return $this->pull();
                }
                $resp->throw();

                foreach ($resp->json('items', []) as $ev) {
                    $changed += $this->applyRemote($ev);
                }

                $pageToken = $resp->json('nextPageToken');
                $nextSync = $resp->json('nextSyncToken');
                if ($nextSync) {
                    Cache::forever(self::SYNC_TOKEN, $nextSync);
                }
            } while ($pageToken);
        } catch (\Throwable $e) {
            Log::warning('GCal pull falhou: '.$e->getMessage());
        } finally {
            self::$syncing = false;
        }

        return $changed;
    }

    /** Aplica um evento do Google na base local (cria/atualiza/remove). */
    private function applyRemote(array $ev): int
    {
        $gid = $ev['id'] ?? null;
        if (! $gid) {
            return 0;
        }

        if (($ev['status'] ?? '') === 'cancelled') {
            $n = AgendaTask::where('google_event_id', $gid)->get();
            foreach ($n as $t) {
                $t->deleteQuietly();
            }

            return $n->count();
        }

        $start = $ev['start']['dateTime'] ?? ($ev['start']['date'] ?? null);
        $end = $ev['end']['dateTime'] ?? ($ev['end']['date'] ?? null);
        if (! $start) {
            return 0;
        }

        AgendaTask::withoutEvents(function () use ($ev, $gid, $start, $end) {
            AgendaTask::updateOrCreate(
                ['google_event_id' => $gid],
                [
                    'title' => $ev['summary'] ?? '(sem título)',
                    'description' => $ev['description'] ?? null,
                    'start_at' => Carbon::parse($start),
                    'end_at' => Carbon::parse($end ?? $start),
                    'done' => false,
                ],
            );
        });

        return 1;
    }

    // ---------------------------------------------------------------- infra

    private function body(AgendaTask $task): array
    {
        $tz = (string) config('googlecal.timezone');
        $start = Carbon::parse($task->start_at);
        $end = Carbon::parse($task->end_at ?? $task->start_at);

        return [
            'summary' => (string) $task->title,
            'description' => (string) ($task->description ?? ''),
            'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => $tz],
            'end' => ['dateTime' => $end->toRfc3339String(), 'timeZone' => $tz],
        ];
    }

    private function cal(): string
    {
        return self::BASE.'/calendars/'.rawurlencode((string) config('googlecal.calendar_id'));
    }

    private function api(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->token())->acceptJson();
    }

    /** Access token via JWT da conta de serviço (cache ~55 min). */
    private function token(): string
    {
        return Cache::remember(self::TOKEN_CACHE, 3300, function () {
            $creds = json_decode((string) file_get_contents((string) config('googlecal.key_path')), true);
            $now = time();

            $jwt = $this->b64([
                'alg' => 'RS256', 'typ' => 'JWT',
            ]).'.'.$this->b64([
                'iss' => $creds['client_email'],
                'scope' => 'https://www.googleapis.com/auth/calendar',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now, 'exp' => $now + 3600,
            ]);

            openssl_sign($jwt, $sig, $creds['private_key'], OPENSSL_ALGO_SHA256);
            $assertion = $jwt.'.'.rtrim(strtr(base64_encode($sig), '+/', '-_'), '=');

            $resp = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
            $resp->throw();

            return (string) $resp->json('access_token');
        });
    }

    private function b64(array $data): string
    {
        return rtrim(strtr(base64_encode(json_encode($data)), '+/', '-_'), '=');
    }
}
