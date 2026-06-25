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
            ['name' => 'srv-empresaA-01', 'enabled' => true, 'available' => 1, 'cpu' => 23, 'ram' => 61, 'disk' => 78],
            ['name' => 'caixa-empresaA-01', 'enabled' => true, 'available' => 1, 'cpu' => 12, 'ram' => 44, 'disk' => 55],
            ['name' => 'srv-empresaB-01', 'enabled' => true, 'available' => 2, 'cpu' => null, 'ram' => null, 'disk' => null],
        ]);
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
}
