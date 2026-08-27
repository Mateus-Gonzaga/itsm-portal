<?php

namespace Database\Seeders;

use App\Models\AgendaTask;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demandas JÁ REALIZADAS de 05/08 a 14/08/2026 (uma tarefa por dia, concluída).
 * Rodar: php artisan db:seed --class=DemandasSemana0508Seeder --force
 * Idempotente (updateOrCreate pelo título) — rodar de novo não duplica.
 */
class DemandasSemana0508Seeder extends Seeder
{
    public function run(): void
    {
        $demandas = [
            '2026-08-05' => [
                'Dado todo o treinamento sobre o financeiro da Mel do Sol',
                'Câmeras da Mel do Sol arrumadas parcialmente',
            ],
            '2026-08-06' => [
                'Atualização de todos os computadores e do servidor da Macarrão',
                'Atualização de todos os computadores e do servidor da A2',
                'Resolução de demandas menores (ex.: AnyDesk / old server da Mel do Sol)',
                'Tentativa de resolução da impressora da filial 11 (Cei)',
            ],
            '2026-08-07' => [
                'Verificação da internet da filial 4 (Cei)',
                'Verificação da nota fiscal na Mel do Sol — explicando o erro à nova funcionária e transmitindo a nota',
                'Ajuda no ponto do Posto Jardim Brasília',
            ],
            '2026-08-08' => [
                'Resolução de demanda via AnyDesk na Salicis',
                'Verificação da TEF da filial 8',
            ],
            '2026-08-10' => [
                'Verificação da impressora da filial 02 (Cei)',
                'Resolução do erro de rede na filial 08 (Cei)',
                'Resolução do erro de conexão na filial 08 (Cei)',
                'Resolução de um erro na Macarrão relacionado à atualização feita',
            ],
            '2026-08-11' => [
                'Ida emergencial à Adji resolvendo erro no computador da expedição',
                'Troca do cabo VGA da expedição',
                'Otimização e limpeza do computador do financeiro',
                'Ligar o servidor da Mel do Sol',
                'Resolução do erro de rede da filial 08 (Cei) — era um cabo que gerou o erro nos dois dias',
                'Cadastro de novo funcionário da Recplan',
            ],
            '2026-08-12' => [
                'Verificação do gerenciador na Recplan',
                'Criação do template de e-mail na Macarrão',
                'Retirada da memória RAM da A2',
                'Troca da fonte da A2',
            ],
            '2026-08-13' => [
                'Junto à Digisat, resolvida a questão do erro da nota; criação do envio por e-mail (o template ainda não estava criado)',
                'Tentativa de resolver as impressoras das filiais 02 e 11 (Cei)',
                'Conversa com a Meire sobre mudar câmeras de local',
            ],
            '2026-08-14' => [
                'Ida à Salicis resolvendo problemas de câmeras',
                'Subida de todo o monitoramento, além de infra e inventário',
            ],
        ];

        foreach ($demandas as $dia => $itens) {
            $inicio = CarbonImmutable::parse($dia.' 08:00:00');

            AgendaTask::updateOrCreate(
                ['title' => 'DEMANDAS '.$inicio->format('d/m/y')],
                [
                    'description' => implode("\n", array_map(fn (string $i) => '• '.$i, $itens)),
                    'start_at' => $inicio,
                    'end_at' => $inicio->setTime(18, 0),
                    'done' => true,
                ],
            );
        }
    }
}
