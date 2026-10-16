<?php

namespace App\Repositories\Glpi\Support;

use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Camada comum de desempenho dos repositórios ApiGlpi*:
 *
 * - Cache de LEITURAS por grupo (tickets, directory, inventory, planning),
 *   separado por escopo de visibilidade (token do usuário x conta de serviço x
 *   entidade) — um cliente nunca recebe o cache de outro.
 * - Invalidação por versão: cada escrita chama flush('grupo') e as próximas
 *   leituras vão ao GLPI de novo (as chaves antigas expiram sozinhas).
 * - Token da CONTA DE SERVIÇO reaproveitado entre requisições (antes era um
 *   initSession novo por página, sem killSession — sessões acumulavam no GLPI).
 * - Timeout nas chamadas e renovação automática do token expirado (401).
 */
final class GlpiCache
{
    /** Validade do token de serviço reaproveitado (GLPI expira sessões ociosas ~24 min). */
    private const TOKEN_TTL = 900;

    public static function enabled(): bool
    {
        return (bool) config('glpi.cache', true);
    }

    /**
     * Lê do cache ou executa $callback. Resultados VAZIOS não são guardados
     * (um erro momentâneo do GLPI não fica "preso" no cache).
     */
    public static function remember(string $group, string $key, int $ttl, Closure $callback, bool $cacheEmpty = false): mixed
    {
        if (! self::enabled()) {
            return $callback();
        }

        $full = 'glpi:'.$group.':'.self::version($group).':'.$key;
        $hit = Cache::get($full);
        if ($hit !== null) {
            return $hit;
        }

        $value = $callback();
        $empty = $value === null || $value === [] || ($value instanceof Collection && $value->isEmpty());
        if (! $empty || ($cacheEmpty && $value !== null)) {
            Cache::put($full, $value, $ttl);
        }

        return $value;
    }

    /** Invalida os grupos informados (após qualquer escrita no GLPI). */
    public static function flush(string ...$groups): void
    {
        foreach ($groups as $group) {
            Cache::forever('glpi:ver:'.$group, self::version($group) + 1);
        }
    }

    /**
     * Escopo de visibilidade da requisição atual: com token do usuário, o GLPI
     * filtra pelo perfil/entidade dele → cache por usuário+sessão; sem token,
     * é a conta de serviço.
     */
    public static function scope(): string
    {
        $token = session('glpi_token');
        if (is_string($token) && $token !== '') {
            return 'u'.(auth()->id() ?? 0).'-'.substr(sha1($token), 0, 10);
        }

        return 'svc';
    }

    /**
     * Token da conta de serviço, reaproveitado entre requisições. Com $entity,
     * a sessão é escopada (changeActiveEntities) e o token fica isolado por
     * entidade+recursividade — sessões com escopos diferentes nunca se misturam.
     */
    public static function serviceToken(
        string $apiUrl,
        string $user,
        string $password,
        string $appToken,
        ?int $entity = null,
        bool $recursive = false,
        bool $fresh = false,
    ): string {
        $key = 'glpi:svc-token:'.sha1($apiUrl.'|'.$user.'|'.($entity ?? 'all').'|'.($recursive ? 1 : 0));
        if ($fresh) {
            Cache::forget($key);
        }

        $init = function () use ($apiUrl, $user, $password, $appToken, $entity, $recursive): string {
            $resp = Http::baseUrl($apiUrl)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(20)
                ->withBasicAuth($user, $password)
                ->withHeaders(array_filter(['App-Token' => $appToken ?: null]))
                ->get('/initSession');
            $resp->throw();

            $token = (string) $resp->json('session_token');
            if ($token === '') {
                throw new RuntimeException('GLPI initSession não retornou session_token.');
            }

            if ($entity !== null) {
                $scoped = Http::baseUrl($apiUrl)
                    ->acceptJson()
                    ->connectTimeout(5)
                    ->timeout(20)
                    ->withHeaders(array_filter(['Session-Token' => $token, 'App-Token' => $appToken ?: null]))
                    ->post('/changeActiveEntities', ['entities_id' => $entity, 'is_recursive' => $recursive]);
                if (! $scoped->successful()) {
                    throw new RuntimeException('Falha ao aplicar o isolamento por entidade no GLPI.');
                }
            }

            return $token;
        };

        if (! self::enabled()) {
            return $init();
        }

        return Cache::remember($key, self::TOKEN_TTL, $init);
    }

    /**
     * Cliente HTTP padrão: timeout e, se o token de serviço expirou (401),
     * renova uma vez e repete. Erros de conexão NÃO são repetidos (um POST
     * poderia ser duplicado).
     *
     * @param  Closure(): string  $freshToken  devolve um token novo (só p/ conta de serviço)
     */
    public static function client(string $apiUrl, string $appToken, string $token, ?Closure $freshToken = null): PendingRequest
    {
        $request = Http::baseUrl($apiUrl)
            ->acceptJson()
            ->connectTimeout(5)
            ->timeout(30)
            ->withHeaders(array_filter([
                'Session-Token' => $token,
                'App-Token' => $appToken ?: null,
            ]));

        if ($freshToken === null) {
            return $request;
        }

        return $request->retry(2, 100, function ($e, PendingRequest $req) use ($freshToken) {
            if ($e instanceof ConnectionException) {
                return false;
            }
            if ($e instanceof RequestException && $e->response->status() === 401) {
                $req->withHeaders(['Session-Token' => $freshToken()]);

                return true;
            }

            return false;
        }, throw: false);
    }

    /**
     * GET paginado: busca todas as páginas (range) até o total informado no
     * Content-Range — antes o limite fixo (0-999/0-499) cortava itens em silêncio.
     *
     * @return array<int, array>
     */
    public static function fetchAll(PendingRequest $client, string $path, array $query = [], int $pageSize = 1000, int $maxItems = 20000): array
    {
        $all = [];
        for ($start = 0; $start < $maxItems; $start += $pageSize) {
            $resp = (clone $client)->get($path, $query + ['range' => $start.'-'.($start + $pageSize - 1)]);
            if (! $resp->successful() || ! is_array($resp->json())) {
                break;
            }

            $rows = $resp->json();
            array_push($all, ...$rows);

            // Content-Range: "0-999/1234" → para quando chegou ao total.
            $total = null;
            if (preg_match('~/(\d+)$~', (string) $resp->header('Content-Range'), $m)) {
                $total = (int) $m[1];
            }
            if (count($rows) < $pageSize || ($total !== null && count($all) >= $total)) {
                break;
            }
        }

        return $all;
    }

    private static function version(string $group): int
    {
        return (int) Cache::get('glpi:ver:'.$group, 0);
    }
}
