<?php

namespace App\Repositories\Zabbix;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Monitoramento de demonstração (modo fake), sem Zabbix real. */
class FakeZabbixRepository implements ZabbixRepositoryInterface
{
    public function groups(): Collection
    {
        return collect([
            ['groupid' => '10', 'name' => 'Clientes/Empresa A/Servidores'],
            ['groupid' => '11', 'name' => 'Clientes/Empresa A/Caixas'],
            ['groupid' => '12', 'name' => 'Clientes/Empresa B/Servidores'],
        ]);
    }

    public function overview(?array $groupIds = null): array
    {
        $h = $this->hosts($groupIds);

        return [
            'hosts' => $h->count(),
            'disponiveis' => $h->where('available', 1)->count(),
            'indisponiveis' => $h->where('available', 2)->count(),
            'problemas' => $this->problems($groupIds)->count(),
            'porSeveridade' => ['Alto' => 1, 'Atenção' => 1],
        ];
    }

    public function hosts(?array $groupIds = null): Collection
    {
        if (is_array($groupIds) && $groupIds === []) {
            return collect();
        }

        return collect([
            ['id' => '101', 'name' => 'srv-empresaA-01', 'enabled' => true, 'available' => 1, 'cpu' => 23, 'ram' => 61, 'disk' => 78],
            ['id' => '102', 'name' => 'caixa-empresaA-01', 'enabled' => true, 'available' => 1, 'cpu' => 12, 'ram' => 44, 'disk' => 55],
            ['id' => '103', 'name' => 'srv-empresaB-01', 'enabled' => true, 'available' => 2, 'cpu' => null, 'ram' => null, 'disk' => null],
        ]);
    }

    public function history(string $hostId, int $hours = 6): array
    {
        // Série sintética (senoide + ruído) só para a demonstração do gráfico.
        $now = time();
        $points = $hours * 12; // 1 ponto a cada 5 min
        $cpu = [];
        $ram = [];
        for ($i = $points; $i >= 0; $i--) {
            $ts = ($now - $i * 300) * 1000;
            $cpu[] = [$ts, round(35 + 20 * sin($i / 6) + random_int(-5, 5), 1)];
            $ram[] = [$ts, round(60 + 12 * sin($i / 9 + 1) + random_int(-4, 4), 1)];
        }

        return ['cpu' => $cpu, 'ram' => $ram];
    }

    public function problems(?array $groupIds = null): Collection
    {
        if (is_array($groupIds) && $groupIds === []) {
            return collect();
        }

        return collect([
            ['severity' => 4, 'severityLabel' => 'Alto', 'color' => 'danger', 'name' => 'Espaço em disco baixo (< 10%)', 'host' => 'srv-empresaB-01', 'since' => CarbonImmutable::now()->subHours(2)],
            ['severity' => 2, 'severityLabel' => 'Atenção', 'color' => 'warning', 'name' => 'Alto uso de CPU (> 90%)', 'host' => 'srv-empresaA-01', 'since' => CarbonImmutable::now()->subMinutes(25)],
        ]);
    }

    public function clientsHealth(array $clientGroups): Collection
    {
        // Dados sintéticos coerentes com os clientes recebidos.
        $i = 0;

        return collect(array_keys($clientGroups))->map(function (string $cliente) use (&$i) {
            $total = 3 + ($i % 3);
            $offline = $i % 2; // 0 ou 1
            $i++;

            return [
                'cliente' => $cliente,
                'total' => $total,
                'online' => $total - $offline,
                'offline' => $offline,
                'alertas' => $offline > 0 ? 1 : 0,
            ];
        })->sortBy('cliente')->values();
    }

    public function problemTrend(?array $groupIds = null, int $hours = 24): array
    {
        if (is_array($groupIds) && $groupIds === []) {
            return [];
        }
        $hours = max(1, min(72, $hours));
        $now = time();
        $out = [];
        for ($t = intdiv($now - $hours * 3600, 3600) * 3600; $t <= $now; $t += 3600) {
            $h = (int) date('G', $t);
            // "madrugada" mais calma, picos à tarde — só para ilustrar a linha.
            $base = $h >= 8 && $h <= 20 ? 2 : 0;
            $out[] = [$t * 1000, max(0, $base + ((int) round(2 * sin($t / 7000)) ))];
        }

        return $out;
    }

    public function diskForecast(?array $groupIds = null, int $days = 7): Collection
    {
        if (is_array($groupIds) && $groupIds === []) {
            return collect();
        }

        return collect([
            ['host' => 'srv-empresaB-01', 'current' => 88, 'perDia' => 1.6, 'dias' => 8],
            ['host' => 'srv-empresaA-01', 'current' => 71, 'perDia' => 0.9, 'dias' => 32],
        ]);
    }

    public function problemHeatmap(?array $groupIds = null, int $days = 7): array
    {
        $dias = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
        $matrix = array_fill(0, 7, array_fill(0, 24, 0));
        if (is_array($groupIds) && $groupIds === []) {
            return ['dias' => $dias, 'matrix' => $matrix, 'max' => 0];
        }
        // Concentra "problemas" na abertura (8-10h) e no fim de tarde (17-19h).
        $max = 0;
        foreach (range(1, 5) as $w) {
            foreach ([8, 9, 17, 18] as $h) {
                $matrix[$w][$h] = ($h < 12 ? 2 : 3) + ($w % 2);
                $max = max($max, $matrix[$w][$h]);
            }
        }

        return ['dias' => $dias, 'matrix' => $matrix, 'max' => $max];
    }

    public function networkTraffic(?array $groupIds = null): Collection
    {
        if (is_array($groupIds) && $groupIds === []) {
            return collect();
        }

        return collect([
            ['host' => 'srv-empresaA-01', 'in' => 45_000_000, 'out' => 12_000_000],
            ['host' => 'caixa-empresaA-01', 'in' => 8_000_000, 'out' => 3_000_000],
        ]);
    }
}
