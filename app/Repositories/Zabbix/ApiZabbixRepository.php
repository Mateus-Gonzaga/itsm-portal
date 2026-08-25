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

    public function clientsHealth(array $clientGroups): Collection
    {
        $allIds = collect($clientGroups)->flatten()->unique()->values()->all();
        if (empty($allIds)) {
            return collect();
        }

        // 1 chamada: hosts (só disponibilidade + grupos), sem métricas de CPU/RAM.
        $rows = $this->call('host.get', [
            'output' => ['hostid', 'name', 'active_available'],
            'selectHostGroups' => ['groupid'],
            'groupids' => $allIds,
        ]);

        // Índice groupid => cliente (para classificar cada host).
        $groupToClient = [];
        foreach ($clientGroups as $cliente => $ids) {
            foreach ($ids as $gid) {
                $groupToClient[(string) $gid] = $cliente;
            }
        }

        $health = [];
        $hostClient = []; // nome do host => cliente (p/ contar alertas)
        foreach ($clientGroups as $cliente => $_) {
            $health[$cliente] = ['cliente' => $cliente, 'total' => 0, 'online' => 0, 'offline' => 0, 'alertas' => 0];
        }
        foreach ($rows as $h) {
            $cliente = null;
            foreach (($h['hostgroups'] ?? $h['groups'] ?? []) as $g) {
                if (isset($groupToClient[(string) ($g['groupid'] ?? '')])) {
                    $cliente = $groupToClient[(string) $g['groupid']];
                    break;
                }
            }
            if ($cliente === null) {
                continue;
            }
            $health[$cliente]['total']++;
            $avail = (int) ($h['active_available'] ?? 0);
            if ($avail === 1) {
                $health[$cliente]['online']++;
            } elseif ($avail === 2) {
                $health[$cliente]['offline']++;
            }
            $hostClient[(string) ($h['name'] ?? '')] = $cliente;
        }

        // Alertas por cliente: reaproveita problems() (problem.get + trigger.get) e mapeia pelo host.
        foreach ($this->problems($allIds) as $p) {
            $cliente = $hostClient[(string) $p['host']] ?? null;
            if ($cliente !== null) {
                $health[$cliente]['alertas']++;
            }
        }

        return collect(array_values($health))->sortBy('cliente')->values();
    }

    public function problemTrend(?array $groupIds = null, int $hours = 24): array
    {
        if (is_array($groupIds) && $groupIds === []) {
            return [];
        }
        $hours = max(1, min(72, $hours));
        $from = time() - $hours * 3600;

        $params = [
            'output' => ['clock'],
            'source' => 0, 'object' => 0, 'value' => 1, // eventos de PROBLEMA (trigger)
            'time_from' => $from,
            'sortfield' => ['clock'], 'sortorder' => 'ASC',
        ];
        if ($groupIds !== null) {
            $params['groupids'] = array_values($groupIds);
        }
        $events = $this->call('event.get', $params);

        // Baldes por hora (preenche zeros para uma linha contínua).
        $buckets = [];
        for ($t = intdiv($from, 3600) * 3600; $t <= time(); $t += 3600) {
            $buckets[$t] = 0;
        }
        foreach ($events as $e) {
            $h = intdiv((int) ($e['clock'] ?? 0), 3600) * 3600;
            if (isset($buckets[$h])) {
                $buckets[$h]++;
            }
        }

        $out = [];
        foreach ($buckets as $t => $c) {
            $out[] = [$t * 1000, $c];
        }

        return $out;
    }

    public function diskForecast(?array $groupIds = null, int $days = 7): Collection
    {
        if (is_array($groupIds) && $groupIds === []) {
            return collect();
        }
        $days = max(2, min(30, $days));

        // Itens de disco (% usado) dos hosts do escopo.
        $params = ['output' => ['itemid', 'key_', 'lastvalue'], 'selectHosts' => ['name'], 'search' => ['key_' => 'vfs.fs']];
        if ($groupIds !== null) {
            $params['groupids'] = array_values($groupIds);
        }
        $items = $this->call('item.get', $params);

        // Por host, guarda o volume MAIS cheio (o mais crítico).
        $byHost = [];
        foreach ($items as $it) {
            if (! str_contains((string) ($it['key_'] ?? ''), 'pused')) {
                continue;
            }
            $host = (string) ($it['hosts'][0]['name'] ?? '—');
            $last = (float) ($it['lastvalue'] ?? 0);
            if (! isset($byHost[$host]) || $last > $byHost[$host]['current']) {
                $byHost[$host] = ['itemid' => (string) $it['itemid'], 'current' => $last];
            }
        }
        if (empty($byHost)) {
            return collect();
        }

        // Tendência (média horária) do período p/ calcular a inclinação.
        $trends = $this->call('trend.get', [
            'output' => ['itemid', 'clock', 'value_avg'],
            'itemids' => array_values(array_map(fn ($h) => $h['itemid'], $byHost)),
            'time_from' => time() - $days * 86400,
        ]);
        $series = [];
        foreach ($trends as $t) {
            $series[(string) $t['itemid']][] = [(int) $t['clock'], (float) $t['value_avg']];
        }

        $out = [];
        foreach ($byHost as $host => $info) {
            $perDia = $this->slopePerDay($series[$info['itemid']] ?? []);
            $current = (int) round($info['current']);
            if ($perDia > 0.05 && $current < 100) { // só quem está enchendo (preventivo)
                $out[] = [
                    'host' => $host,
                    'current' => $current,
                    'perDia' => round($perDia, 2),
                    'dias' => (int) ceil((100 - $current) / $perDia),
                ];
            }
        }
        usort($out, fn ($a, $b) => $a['dias'] <=> $b['dias']);

        return collect($out);
    }

    /** Regressão linear simples → inclinação em % por DIA. */
    private function slopePerDay(array $points): float
    {
        $n = count($points);
        if ($n < 3) {
            return 0.0;
        }
        $sx = $sy = $sxy = $sxx = 0.0;
        $t0 = $points[0][0];
        foreach ($points as [$t, $v]) {
            $x = ($t - $t0) / 86400.0;
            $sx += $x;
            $sy += $v;
            $sxy += $x * $v;
            $sxx += $x * $x;
        }
        $den = $n * $sxx - $sx * $sx;

        return abs($den) < 1e-9 ? 0.0 : ($n * $sxy - $sx * $sy) / $den;
    }

    public function problemHeatmap(?array $groupIds = null, int $days = 7): array
    {
        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $matrix = array_fill(0, 7, array_fill(0, 24, 0));
        if (is_array($groupIds) && $groupIds === []) {
            return ['dias' => $dias, 'matrix' => $matrix, 'max' => 0];
        }
        $days = max(1, min(31, $days));

        $params = ['output' => ['clock'], 'source' => 0, 'object' => 0, 'value' => 1, 'time_from' => time() - $days * 86400];
        if ($groupIds !== null) {
            $params['groupids'] = array_values($groupIds);
        }
        $events = $this->call('event.get', $params);

        $max = 0;
        foreach ($events as $e) {
            $ts = (int) ($e['clock'] ?? 0);
            $w = (int) date('w', $ts);
            $h = (int) date('G', $ts);
            $matrix[$w][$h]++;
            $max = max($max, $matrix[$w][$h]);
        }

        return ['dias' => $dias, 'matrix' => $matrix, 'max' => $max];
    }

    public function networkTraffic(?array $groupIds = null): Collection
    {
        if (is_array($groupIds) && $groupIds === []) {
            return collect();
        }

        $params = ['output' => ['key_', 'lastvalue'], 'selectHosts' => ['name'], 'search' => ['key_' => 'net.if']];
        if ($groupIds !== null) {
            $params['groupids'] = array_values($groupIds);
        }
        $items = $this->call('item.get', $params);

        $byHost = [];
        foreach ($items as $it) {
            $key = (string) ($it['key_'] ?? '');
            $isIn = str_contains($key, 'net.if.in');
            $isOut = str_contains($key, 'net.if.out');
            if (! $isIn && ! $isOut) {
                continue;
            }
            $host = (string) ($it['hosts'][0]['name'] ?? '—');
            $byHost[$host] ??= ['host' => $host, 'in' => 0.0, 'out' => 0.0];
            $byHost[$host][$isIn ? 'in' : 'out'] += (float) ($it['lastvalue'] ?? 0);
        }
        $out = array_values($byHost);
        usort($out, fn ($a, $b) => max($b['in'], $b['out']) <=> max($a['in'], $a['out']));

        return collect(array_slice($out, 0, 10));
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
