<?php

namespace App\Console\Commands;

use App\Support\ZabbixApi;
use Illuminate\Console\Command;

/**
 * Janela de manutenção no Zabbix para publicar o portal sem disparar
 * "fora do ar" à toa (os dados continuam sendo coletados; só os avisos param).
 *
 *   php artisan zabbix:manutencao 15          → silencia o grupo por 15 min
 *   php artisan zabbix:manutencao --encerrar  → encerra agora
 */
class ZabbixManutencao extends Command
{
    protected $signature = 'zabbix:manutencao
        {minutos=15 : Duração da janela (5 a 240)}
        {--encerrar : Remove a janela agora}
        {--group=Portal FOURLINE : Grupo de hosts a silenciar}';

    protected $description = 'Abre/encerra uma janela de manutenção no Zabbix (silencia os avisos do portal durante a publicação)';

    private const NOME = 'Publicação do portal';

    public function handle(ZabbixApi $zbx): int
    {
        try {
            $existentes = collect($zbx->call('maintenance.get', ['output' => ['maintenanceid', 'name'], 'filter' => ['name' => [self::NOME]]]));

            if ($this->option('encerrar')) {
                if ($existentes->isEmpty()) {
                    $this->line('Nenhuma janela de manutenção aberta.');

                    return self::SUCCESS;
                }
                $zbx->call('maintenance.delete', $existentes->pluck('maintenanceid')->all());
                $this->info('Manutenção encerrada — avisos do portal reativados.');

                return self::SUCCESS;
            }

            $min = max(5, min(240, (int) $this->argument('minutos')));
            $group = $zbx->call('hostgroup.get', ['output' => ['groupid'], 'filter' => ['name' => [(string) $this->option('group')]]])[0] ?? null;
            if (! $group) {
                $this->error('Grupo "'.$this->option('group').'" não encontrado no Zabbix.');

                return self::FAILURE;
            }

            // Só existe uma janela com este nome: substitui a anterior.
            if ($existentes->isNotEmpty()) {
                $zbx->call('maintenance.delete', $existentes->pluck('maintenanceid')->all());
            }

            $inicio = time() - 60;
            $fim = time() + $min * 60;
            $zbx->call('maintenance.create', [
                'name' => self::NOME,
                'maintenance_type' => 0, // com coleta de dados
                'active_since' => $inicio,
                'active_till' => $fim,
                'description' => 'Aberta pelo portal (php artisan zabbix:manutencao).',
                'groups' => [['groupid' => $group['groupid']]],
                'timeperiods' => [['timeperiod_type' => 0, 'start_date' => $inicio, 'period' => $fim - $inicio]],
            ]);
            $this->info("Avisos do portal silenciados por {$min} min (até ".date('H:i', $fim).').');
            $this->line('Ao terminar a publicação: php artisan zabbix:manutencao --encerrar');
        } catch (\Throwable $e) {
            $this->error('Falhou: '.$e->getMessage());
            $this->line('O usuário do Zabbix (ZABBIX_USER) precisa de permissão de escrita no grupo do portal.');

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
