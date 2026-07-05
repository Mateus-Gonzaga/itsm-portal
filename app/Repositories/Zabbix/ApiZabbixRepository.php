<?php

namespace App\Repositories\Zabbix;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Implementação real contra a API JSON-RPC do Zabbix (7.0).
 * Login (user.login) em cache; chamadas via header Authorization: Bearer.
 */
class ApiZabbixRepository implements ZabbixRepositoryInterface
{
    private const SEVERITY = [
        0 => ['Não classificado', 'secondary'],
        1 => ['Informação', 'info'],
        2 => ['Atenção', 'warning'],
        3 => ['Médio', 'warning'],
        4 => ['Alto', 'danger'],
        5 => ['Desastre', 'danger'],
    ];

    private ?string $token = null;

    public function __construct(
        private readonly string $url,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public function groups(): Collection
    {
        $rows = $this->call('hostgroup.get', ['output' => ['groupid', 'name'], 'sortfield' => 'name']);

        return collect($rows)->map(fn (array $g) => [
            'groupid' => (string) $g['groupid'],
            'name' => (string) $g['name'],
        ])->values();
    }

    public function overview(?array $groupIds = null): array
    {
        $hosts = $this->hosts($groupIds);
        $problems = $this->problems($groupIds);

        $porSev = [];
        foreach ($problems as $p) {
            $porSev[$p['severityLabel']] = ($porSev[$p['severityLabel']] ?? 0) + 1;
        }

        return [
            'hosts' => $hosts->count(),
            'disponiveis' => $hosts->where('available', 1)->count(),
            'indisponiveis' => $hosts->where('available', 2)->count(),
            'problemas' => $problems->count(),
            'porSeveridade' => $porSev,
        ];
    }

    public function hosts(?array $groupIds = null): Collection
    {
        if (is_array($groupIds) && $groupIds === []) {
            return collect();
        }

        $params = ['output' => ['hostid', 'name', 'status', 'active_available'], 'sortfield' => 'name'];
        if ($groupIds !== null) {
            $params['groupids'] = array_values($groupIds);
        }
        $rows = $this->call('host.get', $params);
        if (empty($rows)) {
            return collect();
        }

        $metrics = $this->metrics(collect($rows)->pluck('hostid')->all());

        return collect($rows)->map(function (array $h) use ($metrics) {
            $m = $metrics[(string) $h['hostid']] ?? [];

            return [
                'id' => (string) ($h['hostid'] ?? ''),
                'name' => (string) ($h['name'] ?? '?'),
                'enabled' => (int) ($h['status'] ?? 0) === 0,
                'available' => (int) ($h['active_available'] ?? 0),
                'cpu' => $m['cpu'] ?? null,
                'ram' => $m['ram'] ?? null,
                'disk' => $m['disk'] ?? null,
            ];
        })->values();
    }

    public function history(string $hostId, int $hours = 6): array
    {
        if ($hostId === '') {
            return ['cpu' => [], 'ram' => []];
        }

        // Descobre os itens de CPU e RAM do host (chaves variam por template).
        $items = $this->call('item.get', [
            'output' => ['itemid', 'key_', 'value_type'],
            'hostids' => [$hostId],
            'search' => ['key_' => ['system.cpu.util', 'vm.memory.util', 'vm.memory.size']],
            'searchByAny' => true,
        ]);

        $cpu = null;
        $ram = null;
        $ramInvert = false; // memory.size[pavailable] = disponível -> inverter p/ usada
        foreach ($items as $it) {
            $key = (string) ($it['key_'] ?? '');
            if ($cpu === null && str_starts_with($key, 'system.cpu.util')) {
                $cpu = $it;
            } elseif ($ram === null && str_starts_with($key, 'vm.memory.util')) {
                $ram = $it;
                $ramInvert = false;
            } elseif ($ram === null && str_starts_with($key, 'vm.memory.size') && str_contains($key, 'pavailable')) {
                $ram = $it;
                $ramInvert = true;
            }
        }

        $from = time() - $hours * 3600;

        return [
            'cpu' => $cpu ? $this->historySeries($cpu, $from, false) : [],
            'ram' => $ram ? $this->historySeries($ram, $from, $ramInvert) : [],
        ];
    }

    /** @return array<int,array{0:int,1:float}> pontos [ts_ms, valor%] em ordem cronológica */
    private function historySeries(array $item, int $from, bool $invert): array
    {
        $rows = $this->call('history.get', [
            'output' => 'extend',
            'itemids' => [(string) $item['itemid']],
            'history' => (int) ($item['value_type'] ?? 0),
            'time_from' => $from,
            'sortfield' => 'clock',
            'sortorder' => 'DESC', // pega os mais recentes; invertemos p/ ordem cronológica
            'limit' => 500,
        ]);

        $out = [];
        foreach ($rows as $r) {
            $v = (float) ($r['value'] ?? 0);
            if ($invert) {
                $v = 100 - $v;
            }
            $out[] = [((int) ($r['clock'] ?? 0)) * 1000, round($v, 1)];
        }

        return array_reverse($out);
    }

    public function problems(?array $groupIds = null): Collection
    {
        if (is_array($groupIds) && $groupIds === []) {
            return collect();
        }

        $params = ['output' => ['eventid', 'objectid', 'name', 'severity', 'clock'], 'recent' => false, 'sortfield' => ['eventid'], 'sortorder' => 'DESC'];
        if ($groupIds !== null) {
            $params['groupids'] = array_values($groupIds);
        }
        $problems = $this->call('problem.get', $params);
        if (empty($problems)) {
            return collect();
        }

        $triggers = $this->call('trigger.get', [
            'triggerids' => collect($problems)->pluck('objectid')->unique()->values()->all(),
            'output' => ['triggerid'],
            'selectHosts' => ['name'],
        ]);
        $hostByTrigger = collect($triggers)->mapWithKeys(fn (array $t) => [
            (string) $t['triggerid'] => (string) ($t['hosts'][0]['name'] ?? '—'),
        ]);

        return collect($problems)->map(function (array $p) use ($hostByTrigger) {
            $sev = (int) ($p['severity'] ?? 0);
            [$label, $color] = self::SEVERITY[$sev] ?? ['?', 'secondary'];

            return [
                'severity' => $sev,
                'severityLabel' => $label,
                'color' => $color,
                'name' => (string) ($p['name'] ?? '—'),
                'host' => $hostByTrigger[(string) ($p['objectid'] ?? '')] ?? '—',
                'since' => CarbonImmutable::createFromTimestamp((int) ($p['clock'] ?? time())),
            ];
        })->sortByDesc('severity')->values();
    }

    // ----------------------------------------------------------------

    /** @return array<string, array{cpu:?int,ram:?int,disk:?int}> hostid => métricas */
    private function metrics(array $hostIds): array
    {
        if (empty($hostIds)) {
            return [];
        }

        // Busca ampla por chaves de CPU/RAM/Disco. Genérico p/ qualquer template
        // (Windows, Linux, caixas): as chaves variam, então tratamos as variações.
        $items = $this->call('item.get', [
            'output' => ['hostid', 'key_', 'lastvalue'],
            'hostids' => array_values($hostIds),
            'search' => ['key_' => ['system.cpu.util', 'vm.memory', 'vfs.fs']],
            'searchByAny' => true,
        ]);

        // 1) Coleta candidatos por host (a ordem dos itens não é garantida).
        $raw = [];
        foreach ($items as $it) {
            $hid = (string) $it['hostid'];
            $key = (string) ($it['key_'] ?? '');
            $val = $it['lastvalue'] ?? null;
            if ($val === null || $val === '') {
                continue;
            }
            $v = (float) $val;
            $raw[$hid] ??= ['cpu' => null, 'memUtil' => null, 'memAvail' => null, 'disk' => null];
            if (str_starts_with($key, 'system.cpu.util')) {
                $raw[$hid]['cpu'] = $v;                                   // % de uso
            } elseif (str_starts_with($key, 'vm.memory.util')) {
                $raw[$hid]['memUtil'] = $v;                               // % usada (preferido)
            } elseif (str_starts_with($key, 'vm.memory.size') && str_contains($key, 'pavailable')) {
                $raw[$hid]['memAvail'] = $v;                              // % disponível (fallback)
            } elseif (str_starts_with($key, 'vfs.fs') && str_contains($key, 'pused')) {
                $raw[$hid]['disk'] = max($raw[$hid]['disk'] ?? 0, $v);    // maior % entre volumes
            }
        }

        // 2) Resolve para CPU/RAM/Disco (% inteiro), com fallback de memória.
        $out = [];
        foreach ($raw as $hid => $r) {
            $ram = $r['memUtil'] ?? ($r['memAvail'] !== null ? 100 - $r['memAvail'] : null);
            $out[$hid] = [
                'cpu' => $r['cpu'] !== null ? (int) round($r['cpu']) : null,
                'ram' => $ram !== null ? (int) round($ram) : null,
                'disk' => $r['disk'] !== null ? (int) round($r['disk']) : null,
            ];
        }

        return $out;
    }

    private function call(string $method, array $params): array
    {
        $resp = Http::asJson()
            ->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->post($this->url, ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => 1]);

        $json = $resp->json();
        if (isset($json['error'])) {
            throw new RuntimeException('Zabbix API '.$method.': '.($json['error']['data'] ?? $json['error']['message'] ?? 'erro'));
        }

        return $json['result'] ?? [];
    }

    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $resp = Http::asJson()->post($this->url, [
            'jsonrpc' => '2.0',
            'method' => 'user.login',
            'params' => ['username' => $this->user, 'password' => $this->password],
            'id' => 1,
        ]);
        $token = $resp->json('result');
        if (! $token) {
            throw new RuntimeException('Zabbix user.login falhou: '.json_encode($resp->json('error')));
        }

        return $this->token = $token;
    }
}
