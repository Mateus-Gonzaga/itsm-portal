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
            '2026-07-13' => [
                'Filial 01 — resolver computador com estática e instalação do nosso sistema',
                'Verificação do servidor da FL 04',
                'Escritório da Drogacei — arrumar a impressora e o computador que não abria o WhatsApp',
                'Reinício do servidor Mel do Sol',
            ],
            '2026-07-14' => [
                'Filial 01 — resolvido o problema de estática',
                'Verificar o erro das notas da Mel do Sol',
                'Fátima 09 — instalação no local',
                'Ajuda na folha de ponto para os meninos do Jardim Brasília',
            ],
            '2026-07-15' => [
                'Instalação do nobreak no financeiro',
                'Parte da resolução da folha de ponto para a Tamires',
                'Identificação do chamado sem dono',
                'Instalação do sistema na filial ao lado do cartório',
            ],
            '2026-07-16' => [
                'Notas da RK',
                'Mel do Sol — ajuda para emitir um relatório com base na região',
                'Finalizada a correção de erros para a Tamires',
                'Conferência e autorização da instalação da internet na Filial 01',
            ],
            '2026-07-17' => [
                'Verificação da possibilidade de o GLPI conter DVR e alarmes, e como monitorar essas coisas',
                'Verificação de computador que não conectava no Vetor',
                'Subir atualização do Zabbix',
                'Filial 03 — implantação do sistema e subida dos computadores',
            ],
            '2026-07-20' => [
                'Instalação dos 3 Offices',
                'FL 09 — impressora de cupom não fiscal que não estava funcionando',
                'Arrumada a folha de ponto do Jardim Brasília',
            ],
            '2026-07-21' => [
                'Coleta das informações do alarme e DVR da filial Fátima 04',
                'Verificação da integridade das câmeras da filial 13 da Cei',
                'Recplan — resolver problema relacionado à internet e depois ao e-mail',
            ],
            '2026-07-22' => [
                'Junsoft — solicitação para apagar os backups do servidor',
                'A2 — subida do sistema e do esquema de monitoramento',
            ],
            '2026-07-23' => [
                'Conferência das câmeras da Cei',
                'Resolução do erro do WhatsApp da Thais',
                'Reinstalação, em todos os computadores, da questão do scanner da impressora',
                'Criação da folha de demandas',
                'Colocar o servidor antigo online na Mel do Sol',
                'Adição de pessoas às câmeras',
            ],
            '2026-07-24' => [
                'Mel do Sol — Resolução do erro 656',
                'Mel do Sol — Resolução do erro nas notas fiscais',
                'Mel do Sol — Adição de dois funcionários ao banco de dados',
                'Mel do Sol — Ajuda para verificar os relatórios',
                'Posto Jardim Brasília — Colocar o DVR novamente em rede e no computador da conveniência',
                'A2 — Correção dos erros relacionados ao UltraViewer',
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
