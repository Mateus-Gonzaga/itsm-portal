<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Configura no Zabbix o monitoramento do próprio portal (idempotente — pode
 * rodar de novo para atualizar a URL):
 *
 *  - grupo + host "Portal FOURLINE Connect";
 *  - cenário web que abre /login a cada 1 min e exige HTTP 200 + "FOURLINE";
 *  - trigger ALTA "fora do ar" (2 falhas seguidas) e MÉDIA-baixa "lento" (>5 s em 5 min);
 *  - ação que avisa o grupo de usuários escolhido por TODAS as mídias dele
 *    (e-mail, WhatsApp…) quando cair e quando voltar.
 *
 * Uso: php artisan monitor:zabbix-portal [--url=http://.../login] [--dry-run]
 */
class ZabbixPortalMonitor extends Command
{
    protected $signature = 'monitor:zabbix-portal
        {--url= : URL do /login a verificar (padrão: PORTAL_PUBLIC_URL/login)}
        {--host=Portal FOURLINE Connect : Nome visível do host no Zabbix}
        {--group=Portal FOURLINE : Grupo de hosts}
        {--usergroup=Zabbix administrators : Grupo de usuários que recebe os avisos}
        {--dry-run : Só mostra o que faria, sem criar nada}';

    protected $description = 'Cria/atualiza no Zabbix o alerta de "portal fora do ar" (cenário web + triggers + ação de aviso)';

    private const TECH_HOST = 'portal-fourline-connect';

    private const SCENARIO = 'Portal - login';

    private const ACTION = 'Portal FOURLINE - avisar equipe';

    private ?string $token = null;

    public function handle(): int
    {
        $url = (string) ($this->option('url') ?: rtrim((string) config('portal.public_url'), '/').'/login');
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error("URL inválida: {$url}. Informe --url= ou PORTAL_PUBLIC_URL no .env.");

            return self::FAILURE;
        }
        $dry = (bool) $this->option('dry-run');

        $this->info('Zabbix: '.config('zabbix.api.url'));
        $this->line("Verificando: {$url}".($dry ? '  (simulação)' : ''));

        try {
            // 1) Grupo de hosts
            $groupName = (string) $this->option('group');
            $group = $this->api('hostgroup.get', ['output' => ['groupid'], 'filter' => ['name' => [$groupName]]])[0] ?? null;
            $groupId = $group['groupid'] ?? null;
            if (! $groupId) {
                $this->line("• Criar grupo de hosts \"{$groupName}\"");
                $groupId = $dry ? '0' : $this->api('hostgroup.create', ['name' => $groupName])['groupids'][0];
            } else {
                $this->line("• Grupo \"{$groupName}\" já existe");
            }

            // 2) Host (sem interface: cenário web roda no próprio servidor Zabbix)
            $host = $this->api('host.get', ['output' => ['hostid'], 'filter' => ['host' => [self::TECH_HOST]]])[0] ?? null;
            $hostId = $host['hostid'] ?? null;
            if (! $hostId) {
                $this->line('• Criar host "'.$this->option('host').'"');
                $hostId = $dry ? '0' : $this->api('host.create', [
                    'host' => self::TECH_HOST,
                    'name' => (string) $this->option('host'),
                    'groups' => [['groupid' => $groupId]],
                    'description' => 'Criado pelo portal (php artisan monitor:zabbix-portal).',
                ])['hostids'][0];
            } else {
                $this->line('• Host já existe');
            }

            // 3) Cenário web
            $steps = [[
                'name' => 'Login',
                'url' => $url,
                'no' => 1,
                'status_codes' => '200',
                'required' => 'FOURLINE',
                'timeout' => '15s',
                'follow_redirects' => 1,
            ]];
            $scenario = $dry && $hostId === '0' ? null : ($this->api('httptest.get', [
                'output' => ['httptestid'], 'hostids' => [$hostId], 'filter' => ['name' => [self::SCENARIO]],
            ])[0] ?? null);
            if ($scenario) {
                $this->line('• Atualizar cenário web (URL/regras)');
                $dry || $this->api('httptest.update', ['httptestid' => $scenario['httptestid'], 'steps' => $steps, 'delay' => '1m', 'retries' => 2]);
            } else {
                $this->line('• Criar cenário web "'.self::SCENARIO.'" (a cada 1 min, exige HTTP 200 e o texto FOURLINE)');
                $dry || $this->api('httptest.create', [
                    'name' => self::SCENARIO, 'hostid' => $hostId, 'delay' => '1m', 'retries' => 2,
                    'agent' => 'Zabbix', 'steps' => $steps,
                ]);
            }

            // 4) Triggers
            $h = self::TECH_HOST;
            $s = self::SCENARIO;
            $this->ensureTrigger($hostId, 'Portal FOURLINE Connect fora do ar',
                "min(/{$h}/web.test.fail[{$s}],#2)>0", 4,
                'O /login do portal falhou em 2 verificações seguidas (sem resposta, erro HTTP ou página inesperada).', $dry);
            $this->ensureTrigger($hostId, 'Portal FOURLINE Connect lento (>5 s)',
                "avg(/{$h}/web.test.time[{$s},Login,resp],5m)>5", 2,
                'Tempo médio de resposta do /login acima de 5 segundos nos últimos 5 minutos.', $dry);

            // 5) Ação de aviso
            $ugName = (string) $this->option('usergroup');
            $ug = $this->api('usergroup.get', ['output' => ['usrgrpid'], 'filter' => ['name' => [$ugName]]])[0] ?? null;
            if (! $ug) {
                throw new RuntimeException("Grupo de usuários \"{$ugName}\" não encontrado no Zabbix (use --usergroup=).");
            }
            $action = $this->api('action.get', ['output' => ['actionid'], 'filter' => ['name' => [self::ACTION], 'eventsource' => 0]])[0] ?? null;
            if ($action) {
                $this->line('• Ação de aviso já existe');
            } else {
                $this->line("• Criar ação \"".self::ACTION."\" → avisa \"{$ugName}\" por todas as mídias (queda e retorno)");
                $dry || $this->api('action.create', [
                    'name' => self::ACTION,
                    'eventsource' => 0,
                    'status' => 0,
                    'esc_period' => '1h',
                    'filter' => ['evaltype' => 0, 'conditions' => [
                        ['conditiontype' => 0, 'operator' => 0, 'value' => (string) $groupId],
                    ]],
                    'operations' => [[
                        'operationtype' => 0,
                        'opmessage' => ['default_msg' => 1, 'mediatypeid' => 0],
                        'opmessage_grp' => [['usrgrpid' => $ug['usrgrpid']]],
                    ]],
                    'recovery_operations' => [[
                        'operationtype' => 11,
                        'opmessage' => ['default_msg' => 1],
                    ]],
                ]);
            }
        } catch (\Throwable $e) {
            $this->error('Falhou: '.$e->getMessage());
            $this->line('Dica: o usuário do .env (ZABBIX_USER) precisa ter permissão de criar hosts, triggers e ações.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info($dry ? 'Simulação concluída (nada foi criado).' : 'Monitoramento configurado.');
        $this->line('Os avisos chegam pelas mídias (e-mail/WhatsApp) cadastradas nos usuários do grupo "'.$ugName.'"');
        $this->line('— confira em Usuários → (usuário) → Mídia, no Zabbix.');

        return self::SUCCESS;
    }

    private function ensureTrigger(string $hostId, string $description, string $expression, int $priority, string $comments, bool $dry): void
    {
        // Compara pelo nome em PHP (o filtro exato da API falhou com "(>5 s)").
        $existing = $dry && $hostId === '0' ? null : collect($this->api('trigger.get', [
            'output' => ['triggerid', 'description'], 'hostids' => [$hostId],
        ]))->first(fn (array $t) => mb_strtolower(trim((string) ($t['description'] ?? ''))) === mb_strtolower($description));

        if ($existing) {
            $this->line("• Trigger \"{$description}\" já existe");

            return;
        }
        $this->line("• Criar trigger \"{$description}\"");
        $dry || $this->api('trigger.create', [
            'description' => $description,
            'expression' => $expression,
            'priority' => $priority,
            'comments' => $comments,
        ]);
    }

    private function api(string $method, array $params): array
    {
        $resp = Http::asJson()->timeout(20)
            ->withHeaders(['Authorization' => 'Bearer '.$this->token()])
            ->post((string) config('zabbix.api.url'), ['jsonrpc' => '2.0', 'method' => $method, 'params' => $params, 'id' => 1]);

        $json = $resp->json();
        if (isset($json['error'])) {
            throw new RuntimeException($method.': '.($json['error']['data'] ?? $json['error']['message'] ?? 'erro'));
        }

        return (array) ($json['result'] ?? []);
    }

    private function token(): string
    {
        if ($this->token !== null) {
            return $this->token;
        }

        $resp = Http::asJson()->timeout(20)->post((string) config('zabbix.api.url'), [
            'jsonrpc' => '2.0',
            'method' => 'user.login',
            'params' => ['username' => (string) config('zabbix.api.user'), 'password' => (string) config('zabbix.api.password')],
            'id' => 1,
        ]);
        $token = $resp->json('result');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Login no Zabbix falhou: '.json_encode($resp->json('error')));
        }

        return $this->token = $token;
    }
}
