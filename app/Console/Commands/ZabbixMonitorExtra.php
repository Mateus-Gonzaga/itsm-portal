<?php

namespace App\Console\Commands;

use App\Support\ZabbixApi;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Monitoramento completo no Zabbix (idempotente — pode rodar de novo):
 *
 *  1. GLPI: cenário web (tela + API) com alerta de "fora do ar";
 *  2. Fila de e-mails do GLPI parada/desativada (via /saude do portal);
 *  3. Saúde do portal: banco, cache (Redis), conexão com o GLPI;
 *  5. "Contêineres": agendador parado, banco/cache fora (pela /saude) e
 *     web/app fora (cenário do monitor:zabbix-portal);
 *  4. --vm=nome:ip  cria o host da VM com o template "Linux by Zabbix agent"
 *     (disco, memória, CPU) — o agente precisa estar instalado na VM;
 *  6. --certificado=dominio  validade do certificado HTTPS (template
 *     "Website certificate by Zabbix agent 2") — usar depois do HTTPS.
 *
 * Tudo vai para o grupo "Portal FOURLINE", que já tem a ação de aviso.
 */
class ZabbixMonitorExtra extends Command
{
    protected $signature = 'monitor:zabbix-extra
        {--glpi-url= : Endereço do GLPI (padrão: o da API do .env, sem /apirest.php)}
        {--saude-url= : Endereço da /saude do portal (padrão: http://<IP do Zabbix>:8083/saude)}
        {--vm=* : nome:ip de VM com Zabbix Agent (pode repetir)}
        {--certificado= : Domínio HTTPS para vigiar o vencimento do certificado}
        {--cert-agente=127.0.0.1 : IP do Zabbix Agent 2 que testa o certificado}
        {--silenciar-servicos= : Serviços do Windows a ignorar no Zabbix, separados por vírgula (ex.: GoogleUpdater,edgeupdate)}
        {--so-silenciar : Executa só o --silenciar-servicos (não mexe no resto)}
        {--group=Portal FOURLINE : Grupo de hosts (o mesmo da ação de aviso)}
        {--dry-run : Só mostra o que faria}';

    protected $description = 'Monitora GLPI, fila de e-mails, saúde do portal (banco/cache/agendador/disco), VMs e certificado no Zabbix';

    private const PORTAL_HOST = 'portal-fourline-connect';

    private const GLPI_HOST = 'glpi-fourline';

    private bool $dry = false;

    public function handle(ZabbixApi $zbx): int
    {
        $this->dry = (bool) $this->option('dry-run');
        $this->info('Zabbix: '.$zbx->url().($this->dry ? '  (simulação)' : ''));

        try {
            if ($servicos = (string) $this->option('silenciar-servicos')) {
                $this->silenciarServicos($zbx, $servicos);
            }
            if ($this->option('so-silenciar')) {
                $this->info($this->dry ? 'Simulação concluída.' : 'Concluído.');

                return self::SUCCESS;
            }

            $groupId = $this->ensureGroup($zbx, (string) $this->option('group'));

            $this->glpi($zbx, $groupId);
            $this->saude($zbx);
            foreach ((array) $this->option('vm') as $vm) {
                $this->vm($zbx, $groupId, (string) $vm);
            }
            if ($dominio = (string) $this->option('certificado')) {
                $this->certificado($zbx, $groupId, $dominio);
            }
        } catch (\Throwable $e) {
            $this->error('Falhou: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($this->dry ? 'Simulação concluída (nada foi criado).' : 'Monitoramento extra configurado.');

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- 1. GLPI

    private function glpi(ZabbixApi $zbx, string $groupId): void
    {
        $base = (string) ($this->option('glpi-url') ?: preg_replace('~/apirest\.php.*$~', '', (string) config('glpi.api.url')));
        $base = rtrim($base, '/');
        if (! filter_var($base, FILTER_VALIDATE_URL)) {
            $this->warn('GLPI: endereço não identificado — use --glpi-url=http://IP:PORTA');

            return;
        }
        $this->line("— GLPI ({$base})");

        $hostId = $this->ensureHost($zbx, self::GLPI_HOST, 'GLPI FOURLINE', $groupId);
        $steps = [
            ['name' => 'Tela', 'url' => $base.'/', 'no' => 1, 'status_codes' => '200', 'required' => 'GLPI', 'timeout' => '15s', 'follow_redirects' => 1],
            // A API responde (mesmo sem login) — 200/400/401 significam "no ar".
            ['name' => 'API', 'url' => $base.'/apirest.php/', 'no' => 2, 'status_codes' => '200,400,401', 'timeout' => '15s', 'follow_redirects' => 1],
        ];
        $this->ensureScenario($zbx, $hostId, 'GLPI - tela e API', $steps);

        $h = self::GLPI_HOST;
        $this->ensureTrigger($zbx, $hostId, 'GLPI fora do ar', "min(/{$h}/web.test.fail[GLPI - tela e API],#2)>0", 4,
            'A tela ou a API do GLPI falhou em 2 verificações seguidas. O portal depende do GLPI.');
        $this->ensureTrigger($zbx, $hostId, 'GLPI lento (>5 s)', "avg(/{$h}/web.test.time[GLPI - tela e API,Tela,resp],5m)>5", 2,
            'Tempo médio da tela do GLPI acima de 5 s em 5 min.');
    }

    // ------------------------------------------------- 2, 3 e 5 — /saude do portal

    private function saude(ZabbixApi $zbx): void
    {
        $token = (string) config('portal.health_token');
        if ($token === '') {
            $this->warn('Saúde do portal: defina PORTAL_HEALTH_TOKEN no .env (e rode optimize:clear) antes — pulando.');

            return;
        }
        $hostIp = parse_url($zbx->url(), PHP_URL_HOST) ?: '127.0.0.1';
        $url = (string) ($this->option('saude-url') ?: "http://{$hostIp}:8083/saude");
        $this->line("— Saúde do portal ({$url}?token=…)");

        $host = $zbx->call('host.get', ['output' => ['hostid'], 'filter' => ['host' => [self::PORTAL_HOST]]])[0] ?? null;
        if (! $host) {
            throw new RuntimeException('Host do portal não existe — rode antes: php artisan monitor:zabbix-portal');
        }
        $hostId = $host['hostid'];

        $masterId = $this->ensureItem($zbx, $hostId, 'portal.saude', [
            'name' => 'Portal: página de saúde (JSON)',
            'type' => 19, 'value_type' => 4, 'delay' => '1m', 'timeout' => '20s',
            'url' => $url.'?token='.$token, 'status_codes' => '200', 'follow_redirects' => 1, 'history' => '1d',
        ], updateUrl: true);

        $dep = [
            ['portal.saude.db', 'Portal: banco de dados (1=ok)', '$.db', 3],
            ['portal.saude.cache', 'Portal: cache Redis (1=ok)', '$.cache', 3],
            ['portal.saude.glpi', 'Portal: conexão com a API do GLPI (1=ok)', '$.glpi', 3],
            ['portal.saude.fila_pendentes', 'GLPI: e-mails aguardando na fila', '$.glpi_fila_pendentes', 0],
            ['portal.saude.fila_atraso_min', 'GLPI: idade do e-mail mais antigo na fila (min)', '$.glpi_fila_atraso_min', 0],
            ['portal.saude.fila_cron', 'GLPI: envio de e-mails ativo (1=sim)', '$.glpi_fila_cron_ativa', 0],
            ['portal.saude.scheduler_min', 'Portal: minutos desde o último ciclo do agendador', '$.scheduler_atraso_min', 0],
            ['portal.saude.disco_livre_pct', 'Portal: disco livre (%)', '$.disco_livre_pct', 0],
        ];
        foreach ($dep as [$key, $name, $path, $type]) {
            $this->ensureItem($zbx, $hostId, $key, [
                'name' => $name, 'type' => 18, 'master_itemid' => $masterId, 'value_type' => $type, 'history' => '30d',
                'preprocessing' => [['type' => 12, 'params' => $path, 'error_handler' => 0, 'error_handler_params' => '']],
            ]);
        }

        $h = self::PORTAL_HOST;
        $triggers = [
            ['Portal: banco de dados indisponível', "last(/{$h}/portal.saude.db)=0", 4, 'O portal não consegue falar com o PostgreSQL (contêiner db).'],
            ['Portal: cache (Redis) indisponível', "last(/{$h}/portal.saude.cache)=0", 3, 'O portal não consegue usar o cache (contêiner redis).'],
            ['Portal: sem conexão com a API do GLPI', "min(/{$h}/portal.saude.glpi,#2)=0", 4, 'Chamados e inventário param de funcionar no portal.'],
            ['GLPI: envio de e-mails desativado', "last(/{$h}/portal.saude.fila_cron)=0", 4, 'A ação automática queuednotification do GLPI está desativada: os clientes não recebem e-mails.'],
            ['GLPI: e-mails parados na fila há mais de 30 min', "last(/{$h}/portal.saude.fila_atraso_min)>30", 3, 'Há e-mails do GLPI esperando envio há mais de 30 min (cron parado ou SMTP com erro).'],
            ['Portal: agendador parado', "last(/{$h}/portal.saude.scheduler_min)>10", 3, 'O contêiner scheduler não roda há mais de 10 min (limpeza de cache, sincronizações).'],
            ['Portal: pouco espaço em disco (<15%)', "last(/{$h}/portal.saude.disco_livre_pct)>=0 and last(/{$h}/portal.saude.disco_livre_pct)<15", 4, 'Disco da VM do portal quase cheio.'],
            ['Portal: página de saúde sem resposta', "nodata(/{$h}/portal.saude.db,5m)=1", 3, 'O Zabbix não consegue ler a /saude do portal há 5 min (app parado ou token errado).'],
        ];
        foreach ($triggers as [$desc, $expr, $prio, $com]) {
            $this->ensureTrigger($zbx, $hostId, $desc, $expr, $prio, $com);
        }
    }

    // ------------------------------------------------------------- 4. VMs

    private function vm(ZabbixApi $zbx, string $groupId, string $spec): void
    {
        if (! preg_match('/^([^:]+):(\d{1,3}(?:\.\d{1,3}){3})$/', trim($spec), $m)) {
            $this->warn("--vm=\"{$spec}\" inválido (use nome:ip)");

            return;
        }
        [$nome, $ip] = [trim($m[1]), $m[2]];
        $this->line("— VM {$nome} ({$ip})");

        $existe = collect($zbx->call('hostinterface.get', ['output' => ['hostid', 'ip'], 'filter' => ['ip' => [$ip], 'type' => 1]]))->first();
        if ($existe) {
            $this->line("• Já existe um host com agente em {$ip} — nada a fazer");

            return;
        }
        $tpl = $zbx->call('template.get', ['output' => ['templateid'], 'filter' => ['host' => ['Linux by Zabbix agent']]])[0] ?? null;
        if (! $tpl) {
            $this->warn('• Template "Linux by Zabbix agent" não encontrado — pulando');

            return;
        }
        $tech = 'vm-'.preg_replace('/[^a-z0-9._-]+/', '-', mb_strtolower($nome));
        $this->line("• Criar host \"{$nome}\" com agente {$ip}:10050 + template Linux (disco, memória, CPU)");
        $this->dry || $zbx->call('host.create', [
            'host' => $tech, 'name' => $nome,
            'groups' => [['groupid' => $groupId]],
            'interfaces' => [['type' => 1, 'main' => 1, 'useip' => 1, 'ip' => $ip, 'dns' => '', 'port' => '10050']],
            'templates' => [['templateid' => $tpl['templateid']]],
        ]);
    }

    // -------------------------------------------------------- 6. Certificado

    private function certificado(ZabbixApi $zbx, string $groupId, string $dominio): void
    {
        $dominio = preg_replace('~^https?://~', '', rtrim($dominio, '/'));
        $this->line("— Certificado HTTPS de {$dominio}");
        $tpl = $zbx->call('template.get', ['output' => ['templateid'], 'filter' => ['host' => ['Website certificate by Zabbix agent 2']]])[0] ?? null;
        if (! $tpl) {
            $this->warn('• Template "Website certificate by Zabbix agent 2" não encontrado — pulando');

            return;
        }
        $tech = 'cert-'.preg_replace('/[^a-z0-9._-]+/', '-', mb_strtolower($dominio));
        if ($zbx->call('host.get', ['output' => ['hostid'], 'filter' => ['host' => [$tech]]])) {
            $this->line('• Já monitorado');

            return;
        }
        $this->line('• Criar host de certificado (avisa antes de vencer)');
        $this->dry || $zbx->call('host.create', [
            'host' => $tech, 'name' => "Certificado {$dominio}",
            'groups' => [['groupid' => $groupId]],
            'interfaces' => [['type' => 1, 'main' => 1, 'useip' => 1, 'ip' => (string) $this->option('cert-agente'), 'dns' => '', 'port' => '10050']],
            'templates' => [['templateid' => $tpl['templateid']]],
            'macros' => [['macro' => '{$CERT.WEBSITE.HOSTNAME}', 'value' => $dominio]],
        ]);
    }

    // --------------------------------------------- serviços do Windows (ruído)

    /**
     * Acrescenta serviços à lista de exclusão {$SERVICE.NAME.NOT_MATCHES} dos
     * templates Windows — o Zabbix para de criar alertas "serviço X parado" para eles
     * (os itens já descobertos somem na próxima descoberta).
     */
    private function silenciarServicos(ZabbixApi $zbx, string $lista): void
    {
        $nomes = collect(explode(',', $lista))->map(fn ($s) => trim($s))->filter()->values();
        if ($nomes->isEmpty()) {
            return;
        }
        $this->line('— Silenciar serviços do Windows: '.$nomes->implode(', '));

        $templates = $zbx->call('template.get', [
            'output' => ['templateid', 'host'],
            'filter' => ['host' => ['Windows by Zabbix agent', 'Windows by Zabbix agent active']],
        ]);
        if (! $templates) {
            $this->warn('• Templates Windows by Zabbix agent não encontrados');

            return;
        }

        foreach ($templates as $t) {
            $macro = $zbx->call('usermacro.get', [
                'output' => ['hostmacroid', 'value'],
                'hostids' => [$t['templateid']],
                'filter' => ['macro' => '{$SERVICE.NAME.NOT_MATCHES}'],
            ])[0] ?? null;
            if (! $macro) {
                $this->warn("• {$t['host']}: macro {\$SERVICE.NAME.NOT_MATCHES} não encontrada — pulando");

                continue;
            }

            $valor = (string) $macro['value'];
            $faltando = $nomes->reject(fn ($n) => stripos($valor, $n) !== false);
            if ($faltando->isEmpty()) {
                $this->line("• {$t['host']}: já ignora esses serviços");

                continue;
            }
            $extra = $faltando->map(fn ($n) => preg_quote($n, '/').'.*')->implode('|');
            // Padrão do template: ^(?:A|B|C)$ — insere antes do fechamento.
            $novo = preg_match('/^\^\(\?:(.*)\)\$$/s', $valor, $m)
                ? '^(?:'.$m[1].'|'.$extra.')$'
                : '(?:'.$valor.')|^(?:'.$extra.')$';

            $this->line("• {$t['host']}: ignorar ".$faltando->implode(', '));
            $this->dry || $zbx->call('usermacro.update', ['hostmacroid' => $macro['hostmacroid'], 'value' => $novo]);
        }
    }

    // ------------------------------------------------------------- helpers

    private function ensureGroup(ZabbixApi $zbx, string $name): string
    {
        $g = $zbx->call('hostgroup.get', ['output' => ['groupid'], 'filter' => ['name' => [$name]]])[0] ?? null;
        if ($g) {
            return $g['groupid'];
        }
        $this->line("• Criar grupo \"{$name}\"");

        return $this->dry ? '0' : $zbx->call('hostgroup.create', ['name' => $name])['groupids'][0];
    }

    private function ensureHost(ZabbixApi $zbx, string $tech, string $name, string $groupId): string
    {
        $h = $zbx->call('host.get', ['output' => ['hostid'], 'filter' => ['host' => [$tech]]])[0] ?? null;
        if ($h) {
            $this->line("• Host \"{$name}\" já existe");

            return $h['hostid'];
        }
        $this->line("• Criar host \"{$name}\"");

        return $this->dry ? '0' : $zbx->call('host.create', [
            'host' => $tech, 'name' => $name, 'groups' => [['groupid' => $groupId]],
            'description' => 'Criado pelo portal (php artisan monitor:zabbix-extra).',
        ])['hostids'][0];
    }

    private function ensureScenario(ZabbixApi $zbx, string $hostId, string $name, array $steps): void
    {
        $s = $hostId === '0' ? null : ($zbx->call('httptest.get', ['output' => ['httptestid'], 'hostids' => [$hostId], 'filter' => ['name' => [$name]]])[0] ?? null);
        if ($s) {
            $this->line("• Atualizar cenário \"{$name}\"");
            $this->dry || $zbx->call('httptest.update', ['httptestid' => $s['httptestid'], 'steps' => $steps, 'delay' => '1m', 'retries' => 2]);

            return;
        }
        $this->line("• Criar cenário \"{$name}\" (a cada 1 min)");
        $this->dry || $zbx->call('httptest.create', ['name' => $name, 'hostid' => $hostId, 'delay' => '1m', 'retries' => 2, 'agent' => 'Zabbix', 'steps' => $steps]);
    }

    private function ensureItem(ZabbixApi $zbx, string $hostId, string $key, array $params, bool $updateUrl = false): string
    {
        $i = $hostId === '0' ? null : ($zbx->call('item.get', ['output' => ['itemid', 'url'], 'hostids' => [$hostId], 'filter' => ['key_' => [$key]]])[0] ?? null);
        if ($i) {
            if ($updateUrl && ($i['url'] ?? '') !== ($params['url'] ?? '')) {
                $this->line("• Atualizar endereço do item {$key}");
                $this->dry || $zbx->call('item.update', ['itemid' => $i['itemid'], 'url' => $params['url']]);
            } else {
                $this->line("• Item {$key} já existe");
            }

            return $i['itemid'];
        }
        $this->line("• Criar item {$key}");
        if ($this->dry) {
            return '0';
        }
        if (isset($params['master_itemid']) && $params['master_itemid'] === '0') {
            return '0';
        }

        return $zbx->call('item.create', ['hostid' => $hostId, 'key_' => $key] + $params)['itemids'][0];
    }

    private function ensureTrigger(ZabbixApi $zbx, string $hostId, string $description, string $expression, int $priority, string $comments): void
    {
        $existing = $hostId === '0' ? null : collect($zbx->call('trigger.get', ['output' => ['triggerid', 'description'], 'hostids' => [$hostId]]))
            ->first(fn ($t) => mb_strtolower(trim((string) $t['description'])) === mb_strtolower($description));
        if ($existing) {
            $this->line("• Alerta \"{$description}\" já existe");

            return;
        }
        $this->line("• Criar alerta \"{$description}\"");
        $this->dry || $zbx->call('trigger.create', [
            'description' => $description, 'expression' => $expression, 'priority' => $priority, 'comments' => $comments,
        ]);
    }
}
