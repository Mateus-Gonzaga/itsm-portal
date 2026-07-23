<?php

namespace Database\Seeders;

use App\Models\AgendaTask;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Lança na agenda as demandas JÁ REALIZADAS (uma tarefa por dia, concluída).
 * Rodar: php artisan db:seed --class=DemandasJulhoSeeder --force
 * É idempotente (updateOrCreate pelo título) — rodar de novo não duplica.
 */
class DemandasJulhoSeeder extends Seeder
{
    public function run(): void
    {
        $demandas = [
            '2026-07-06' => [
                'Negar acesso às câmeras para sub gerente da 14, pois este não era um caso diferente',
                'Atualização completa da folha da Recon (chamada com a Raquel)',
                'Resolução do problema de ponto com a Tamires',
                'Reinício do servidor da Mel do Sol',
            ],
            '2026-07-07' => [
                'Instalação do SSD na FL01-DF',
                'Resolução do problema de vídeo do computador da perfumaria',
                'Configuração e instalação do Windows e pacote Office no computador em que o Windows foi instalado',
                'Acompanhamento do erro da SEFAZ e explicação do porquê as notas não estavam sendo transmitidas',
                'Verificação dos contratos de internet da Drogaria Fátima',
            ],
            '2026-07-08' => [
                'Resolução do erro de ponto da Apoio',
                'Instalação de SSD',
                'Pacote Office',
                'Aplicativo que eles utilizam na loja',
                'Instalação do GLPI/Zabbix na filial 04 em conjunto com a TI',
            ],
            '2026-07-09' => [
                'Instalação do alarme no novo telefone do Paulo (DF-02)',
                'Ajuda à Raissa da ADTAG com erros no ponto',
                'Ajuda em problemas menores: erros nos computadores e gerenciadores (ex.: Recplan e Inga)',
                'Feita parte das notas da RK',
            ],
            '2026-07-10' => [
                'Demanda da contabilidade: o computador não abria o programa do governo — refiz o banco de dados e testei todas as hipóteses; também baixei o pacote Office novo para a funcionária',
                'Impressora da filial 15 que, após a troca do roteador, não conectava de jeito algum na rede interna',
                'Conversão de um documento para o Halley',
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
