<?php

namespace App\Repositories\Zabbix;

use Illuminate\Support\Collection;

/**
 * Monitoramento via API do Zabbix (JSON-RPC). Alimenta a aba Dashboards.
 * Filtros por host group (groupIds) permitem a navegação por cliente/tipo.
 * Trocável: Fake (demo) / Api (Zabbix real) via ZABBIX_DRIVER.
 *
 * Convenção de grupos (multi-tenant): "Clientes/<Cliente>/<Servidores|Caixas>".
 */
interface ZabbixRepositoryInterface
{
    /** @return Collection<int, array{groupid:string,name:string}> */
    public function groups(): Collection;

    /**
     * @param  array<int,string>|null  $groupIds  null = todos; [] = nenhum
     * @return array{hosts:int,disponiveis:int,indisponiveis:int,problemas:int,porSeveridade:array<string,int>}
     */
    public function overview(?array $groupIds = null): array;

    /**
     * @param  array<int,string>|null  $groupIds
     * @return Collection<int, array{id:string,name:string,enabled:bool,available:int,cpu:?int,ram:?int,disk:?int}>
     */
    public function hosts(?array $groupIds = null): Collection;

    /**
     * Histórico (série temporal) de CPU e RAM de um host nas últimas $hours horas.
     * Cada ponto é [timestamp_ms, valor_percentual].
     *
     * @return array{cpu:array<int,array{0:int,1:float}>, ram:array<int,array{0:int,1:float}>}
     */
    public function history(string $hostId, int $hours = 6): array;

    /**
     * @param  array<int,string>|null  $groupIds
     * @return Collection<int, array{severity:int,severityLabel:string,color:string,name:string,host:string,since:\Carbon\CarbonImmutable}>
     */
    public function problems(?array $groupIds = null): Collection;

    /**
     * Saúde resumida por cliente (leve: usada na Visão Geral).
     *
     * @param  array<string, array<int,string>>  $clientGroups  nome do cliente => groupIds
     * @return Collection<int, array{cliente:string,total:int,online:int,offline:int,alertas:int}>
     */
    public function clientsHealth(array $clientGroups): Collection;

    /**
     * Quantidade de NOVOS problemas por hora nas últimas $hours horas.
     *
     * @param  array<int,string>|null  $groupIds
     * @return array<int, array{0:int,1:int}>  cada ponto é [timestamp_ms, contagem]
     */
    public function problemTrend(?array $groupIds = null, int $hours = 24): array;
}
