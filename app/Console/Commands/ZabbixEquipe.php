<?php

namespace App\Console\Commands;

use App\Support\ZabbixApi;
use Illuminate\Console\Command;

/**
 * Manutenção da equipe/alertas no Zabbix (rodar na VM do portal):
 *
 *   php artisan zabbix:equipe --listar
 *       Lista usuários, grupos e mídias cadastradas.
 *   php artisan zabbix:equipe --midia=joao:joao@fourline.com.br --midia=halley:halley@fourline.com.br
 *       Cadastra e-mail (tipo de mídia "Email") nos usuários informados (login:email).
 *   php artisan zabbix:equipe --limpar-duplicados
 *       Remove triggers repetidos do host do portal (mantém o mais antigo).
 *
 * Use --dry-run para só simular.
 */
class ZabbixEquipe extends Command
{
    protected $signature = 'zabbix:equipe
        {--listar : Lista usuários e mídias}
        {--midia=* : login:email para cadastrar e-mail de aviso (pode repetir)}
        {--limpar-duplicados : Remove triggers duplicados do host do portal}
        {--grupo=Zabbix administrators : Grupo que recebe os avisos do portal}
        {--dry-run : Só mostra o que faria}';

    protected $description = 'Lista a equipe do Zabbix, cadastra e-mails de aviso e limpa triggers duplicados do portal';

    public function handle(ZabbixApi $zbx): int
    {
        $dry = (bool) $this->option('dry-run');
        if (! $this->option('listar') && ! $this->option('midia') && ! $this->option('limpar-duplicados')) {
            $this->line('Nada a fazer. Use --listar, --midia=login:email ou --limpar-duplicados (veja --help).');

            return self::SUCCESS;
        }

        try {
            if ($this->option('limpar-duplicados')) {
                $this->limparDuplicados($zbx, $dry);
            }
            if ($this->option('midia')) {
                $this->cadastrarMidias($zbx, (array) $this->option('midia'), $dry);
            }
            if ($this->option('listar')) {
                $this->listar($zbx);
            }
        } catch (\Throwable $e) {
            $this->error('Falhou: '.$e->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function listar(ZabbixApi $zbx): void
    {
        $tipos = collect($zbx->call('mediatype.get', ['output' => ['mediatypeid', 'name', 'status']]))
            ->keyBy('mediatypeid');
        $grupo = (string) $this->option('grupo');

        $rows = collect($zbx->call('user.get', [
            'output' => ['userid', 'username', 'name', 'surname'],
            'selectMedias' => ['mediatypeid', 'sendto', 'active'],
            'selectUsrgrps' => ['name'],
        ]))->map(function (array $u) use ($tipos, $grupo) {
            $midias = collect($u['medias'] ?? [])->map(function ($m) use ($tipos) {
                $nome = $tipos[$m['mediatypeid']]['name'] ?? '?';
                $dest = is_array($m['sendto']) ? implode(', ', $m['sendto']) : (string) $m['sendto'];

                return $nome.': '.$dest.((string) $m['active'] === '0' ? '' : ' (inativa)');
            })->implode(' | ');
            $grupos = collect($u['usrgrps'] ?? [])->pluck('name');

            return [
                $u['username'],
                trim(($u['name'] ?? '').' '.($u['surname'] ?? '')),
                $grupos->contains($grupo) ? 'sim' : 'NÃO',
                $midias !== '' ? $midias : '— nenhuma —',
            ];
        });

        $this->newLine();
        $this->info('Usuários do Zabbix');
        $this->table(['Login', 'Nome', "No grupo \"{$grupo}\"?", 'Mídias'], $rows->all());
        $this->line('Só recebe o aviso do portal quem está no grupo E tem mídia ativa.');
    }

    private function cadastrarMidias(ZabbixApi $zbx, array $pares, bool $dry): void
    {
        $email = collect($zbx->call('mediatype.get', ['output' => ['mediatypeid', 'name', 'type', 'status']]))
            ->first(fn ($t) => (int) $t['type'] === 0 && (int) $t['status'] === 0); // 0 = e-mail, ativo
        if (! $email) {
            throw new \RuntimeException('Nenhum tipo de mídia de e-mail ATIVO no Zabbix (Alertas → Tipos de mídia).');
        }

        foreach ($pares as $par) {
            if (! str_contains($par, ':')) {
                $this->warn("Ignorado \"{$par}\": use login:email");

                continue;
            }
            [$login, $destino] = array_map('trim', explode(':', $par, 2));
            if (! filter_var($destino, FILTER_VALIDATE_EMAIL)) {
                $this->warn("Ignorado \"{$par}\": e-mail inválido");

                continue;
            }

            $user = $zbx->call('user.get', [
                'output' => ['userid', 'username'],
                'filter' => ['username' => [$login]],
                'selectMedias' => 'extend',
            ])[0] ?? null;
            if (! $user) {
                $this->warn("Usuário \"{$login}\" não existe no Zabbix — crie em Usuários → Usuários.");

                continue;
            }

            $medias = collect($user['medias'] ?? []);
            $jaTem = $medias->contains(fn ($m) => (string) $m['mediatypeid'] === (string) $email['mediatypeid']
                && in_array(mb_strtolower($destino), array_map('mb_strtolower', (array) $m['sendto']), true));
            if ($jaTem) {
                $this->line("• {$login}: já recebe em {$destino}");

                continue;
            }

            // user.update substitui a lista inteira: mantém as atuais + a nova.
            $novas = $medias->map(fn ($m) => [
                'mediatypeid' => $m['mediatypeid'],
                'sendto' => $m['sendto'],
                'active' => $m['active'],
                'severity' => $m['severity'],
                'period' => $m['period'],
            ])->push([
                'mediatypeid' => $email['mediatypeid'],
                'sendto' => [$destino],
                'active' => 0,          // 0 = ativa
                'severity' => 63,       // todas as severidades
                'period' => '1-7,00:00-24:00',
            ])->values()->all();

            $this->line("• {$login}: cadastrar e-mail {$destino} (".$email['name'].')');
            $dry || $zbx->call('user.update', ['userid' => $user['userid'], 'medias' => $novas]);
        }
    }

    private function limparDuplicados(ZabbixApi $zbx, bool $dry): void
    {
        $host = $zbx->call('host.get', ['output' => ['hostid'], 'filter' => ['host' => ['portal-fourline-connect']]])[0] ?? null;
        if (! $host) {
            $this->warn('Host do portal (portal-fourline-connect) não encontrado — rode monitor:zabbix-portal antes.');

            return;
        }

        $triggers = collect($zbx->call('trigger.get', [
            'output' => ['triggerid', 'description'],
            'hostids' => [$host['hostid']],
        ]));
        $remover = $triggers->groupBy(fn ($t) => mb_strtolower(trim($t['description'])))
            ->flatMap(fn ($grupo) => $grupo->sortBy(fn ($t) => (int) $t['triggerid'])->slice(1))
            ->values();

        if ($remover->isEmpty()) {
            $this->line('• Nenhum trigger duplicado no host do portal.');

            return;
        }
        foreach ($remover as $t) {
            $this->line("• Remover duplicado: \"{$t['description']}\" (id {$t['triggerid']})");
        }
        $dry || $zbx->call('trigger.delete', $remover->pluck('triggerid')->all());
    }
}
