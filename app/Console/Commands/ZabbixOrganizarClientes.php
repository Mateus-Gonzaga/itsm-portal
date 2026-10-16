<?php

namespace App\Console\Commands;

use App\Repositories\Glpi\Support\GlpiCache;
use App\Support\ZabbixApi;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Organiza os hosts do Zabbix nos grupos "Clientes/<Cliente>/<Servidores|Caixas>"
 * (o que a aba Dashboards usa para navegar por cliente). Duas etapas:
 *
 * 1) php artisan zabbix:organizar-clientes --sugerir
 *    Cruza cada host do Zabbix com o computador de mesmo nome no inventário do
 *    GLPI (que já sabe a filial/cliente) e gera storage/app/zabbix-clientes.csv
 *    com cliente e tipo sugeridos. Revise/complete o arquivo (nano).
 *
 * 2) php artisan zabbix:organizar-clientes --aplicar [--dry-run]
 *    Lê o CSV, cria os grupos que faltarem e ADICIONA cada host ao grupo
 *    (não remove nenhum grupo que o host já tenha).
 */
class ZabbixOrganizarClientes extends Command
{
    protected $signature = 'zabbix:organizar-clientes
        {--sugerir : Gera o CSV com as sugestões}
        {--aplicar : Aplica o CSV no Zabbix}
        {--arquivo=zabbix-clientes.csv : Nome do CSV (em storage/app)}
        {--dry-run : Só mostra o que faria}';

    protected $description = 'Organiza os hosts do Zabbix em Clientes/<Cliente>/<Servidores|Caixas> cruzando com o inventário do GLPI';

    private const TIPOS = ['Servidores', 'Caixas'];

    public function handle(ZabbixApi $zbx): int
    {
        $path = storage_path('app/'.basename((string) $this->option('arquivo')));

        try {
            if ($this->option('sugerir')) {
                return $this->sugerir($zbx, $path);
            }
            if ($this->option('aplicar')) {
                return $this->aplicar($zbx, $path, (bool) $this->option('dry-run'));
            }
        } catch (\Throwable $e) {
            $this->error('Falhou: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('Use --sugerir (gera o CSV) e depois --aplicar.');

        return self::SUCCESS;
    }

    private function sugerir(ZabbixApi $zbx, string $path): int
    {
        // Hosts do Zabbix (ignora o host de monitoramento do próprio portal).
        $hosts = collect($zbx->call('host.get', [
            'output' => ['hostid', 'host', 'name'],
            'selectHostGroups' => ['name'],
        ]))->reject(fn ($h) => $h['host'] === 'portal-fourline-connect')->values();

        // Computadores do GLPI por nome → cliente (trecho após "CLIENTES" na entidade).
        $glpi = $this->glpiComputersByName();
        $this->line('Hosts no Zabbix: '.$hosts->count().' · computadores no GLPI: '.count($glpi));

        $rows = [];
        $achados = 0;
        foreach ($hosts as $h) {
            $atual = collect($h['hostgroups'] ?? [])->pluck('name')
                ->first(fn ($n) => str_starts_with($n, 'Clientes/'));
            $match = $glpi[$this->norm($h['host'])] ?? $glpi[$this->norm($h['name'])] ?? null;
            $cliente = $match['cliente'] ?? '';
            $achados += $cliente !== '' ? 1 : 0;

            // Se já está organizado, mantém o que existe.
            if ($atual) {
                $p = explode('/', $atual);
                $cliente = $p[1] ?? $cliente;
                $tipo = $p[2] ?? '';
            } else {
                $tipo = $this->tipoSugerido($h['host'].' '.$h['name'].' '.($match['os'] ?? ''));
            }

            $rows[] = [$h['host'], $h['name'], $cliente, $tipo, $match['entidade'] ?? '', $atual ?: ''];
        }

        $fh = fopen($path, 'w');
        fwrite($fh, "\xEF\xBB\xBF"); // BOM: abre certo no Excel
        fputcsv($fh, ['host', 'nome_visivel', 'cliente', 'tipo', 'entidade_glpi', 'grupo_atual'], ';');
        foreach ($rows as $r) {
            fputcsv($fh, $r, ';');
        }
        fclose($fh);

        $this->table(['Host', 'Cliente sugerido', 'Tipo', 'Entidade no GLPI'],
            collect($rows)->map(fn ($r) => [$r[0], $r[2] ?: '—', $r[3] ?: '—', Str::limit($r[4], 45)])->all());
        $this->info("CSV gerado: {$path}");
        $this->line("Cliente encontrado no GLPI para {$achados} de ".count($rows).' host(s).');
        $this->line('Revise/complete as colunas "cliente" e "tipo" (Servidores ou Caixas). Linhas sem cliente ou tipo são ignoradas.');
        $this->line('Depois: php artisan zabbix:organizar-clientes --aplicar --dry-run');

        return self::SUCCESS;
    }

    private function aplicar(ZabbixApi $zbx, string $path, bool $dry): int
    {
        if (! is_file($path)) {
            $this->error("CSV não encontrado: {$path}. Rode --sugerir antes.");

            return self::FAILURE;
        }

        $fh = fopen($path, 'r');
        $header = fgetcsv($fh, 0, ';');
        $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
        $idx = array_flip($header);
        $plano = [];
        while (($r = fgetcsv($fh, 0, ';')) !== false) {
            $host = trim((string) ($r[$idx['host']] ?? ''));
            $cliente = trim(str_replace('/', '-', (string) ($r[$idx['cliente']] ?? '')));
            $tipo = Str::ucfirst(mb_strtolower(trim((string) ($r[$idx['tipo']] ?? ''))));
            if ($host === '' || $cliente === '' || $tipo === '') {
                continue;
            }
            if (! in_array($tipo, self::TIPOS, true)) {
                $this->warn("{$host}: tipo \"{$tipo}\" inválido (use Servidores ou Caixas) — ignorado");

                continue;
            }
            $plano["Clientes/{$cliente}/{$tipo}"][] = $host;
        }
        fclose($fh);

        if ($plano === []) {
            $this->warn('Nenhuma linha com cliente e tipo preenchidos.');

            return self::SUCCESS;
        }

        $hostIds = collect($zbx->call('host.get', ['output' => ['hostid', 'host']]))->pluck('hostid', 'host');
        $grupos = collect($zbx->call('hostgroup.get', ['output' => ['groupid', 'name']]))->pluck('groupid', 'name');

        foreach ($plano as $grupo => $nomes) {
            $ids = collect($nomes)->map(fn ($n) => $hostIds[$n] ?? null)->filter()->values();
            $faltando = collect($nomes)->reject(fn ($n) => isset($hostIds[$n]));
            foreach ($faltando as $n) {
                $this->warn("Host \"{$n}\" não existe no Zabbix — ignorado");
            }
            if ($ids->isEmpty()) {
                continue;
            }

            $groupId = $grupos[$grupo] ?? null;
            if (! $groupId) {
                $this->line("• Criar grupo \"{$grupo}\"");
                $groupId = $dry ? '0' : $zbx->call('hostgroup.create', ['name' => $grupo])['groupids'][0];
            }
            $this->line("• \"{$grupo}\" ← ".$ids->count().' host(s): '.collect($nomes)->filter(fn ($n) => isset($hostIds[$n]))->implode(', '));
            $dry || $zbx->call('hostgroup.massadd', [
                'groups' => [['groupid' => $groupId]],
                'hosts' => $ids->map(fn ($id) => ['hostid' => $id])->all(),
            ]);
        }

        $this->info($dry ? 'Simulação concluída (nada foi alterado).' : 'Hosts organizados. Recarregue a aba Dashboards do portal.');

        return self::SUCCESS;
    }

    /** @return array<string, array{cliente:string, entidade:string, os:string}> nome normalizado => dados */
    private function glpiComputersByName(): array
    {
        $url = (string) config('glpi.api.url');
        if ($url === '' || config('glpi.driver') !== 'api') {
            $this->warn('GLPI não configurado (GLPI_DRIVER=api) — sugestões só pelo nome do host.');

            return [];
        }

        $token = GlpiCache::serviceToken($url, (string) config('glpi.api.user'), (string) config('glpi.api.password'), (string) config('glpi.api.app_token'));
        $client = GlpiCache::client($url, (string) config('glpi.api.app_token'), $token);

        $map = [];
        foreach (GlpiCache::fetchAll($client, '/Computer', ['expand_dropdowns' => 'true']) as $c) {
            $entidade = html_entity_decode((string) ($c['entities_id'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $parts = array_map('trim', explode('>', $entidade));
            $i = array_search('CLIENTES', $parts, true);
            $cliente = ($i !== false && isset($parts[$i + 1])) ? $parts[$i + 1] : '';
            $nome = $this->norm((string) ($c['name'] ?? ''));
            if ($nome !== '' && $cliente !== '') {
                $map[$nome] = ['cliente' => $cliente, 'entidade' => implode(' > ', array_slice($parts, max(0, (int) $i + 1))), 'os' => (string) ($c['operatingsystems_id'] ?? '')];
            }
        }

        return $map;
    }

    /** Servidor se o nome/sistema indicar; caixa (PDV) se indicar; senão em branco para você decidir. */
    private function tipoSugerido(string $texto): string
    {
        $t = mb_strtoupper($texto);
        if (preg_match('/\b(SRV|SERV|SERVER|SERVIDOR|DC\d*|NAS|HOST|VM)\b|WINDOWS SERVER|LINUX/', $t)) {
            return 'Servidores';
        }
        if (preg_match('/\b(PDV|CX|CAIXA|CAI|POS|CHECKOUT)\b|PDV\d|CX\d|CAIXA\d/', $t)) {
            return 'Caixas';
        }

        return '';
    }

    private function norm(string $s): string
    {
        return mb_strtolower(trim(Str::ascii($s)));
    }
}
