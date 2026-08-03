<?php

namespace Database\Seeders;

use App\Models\AgendaTask;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Demandas JÁ REALIZADAS da semana 27–30/07/2026 (uma tarefa por dia, concluída).
 * Rodar: php artisan db:seed --class=DemandasSemana2707Seeder --force
 * Idempotente (updateOrCreate pelo título) — rodar de novo não duplica.
 */
class DemandasSemana2707Seeder extends Seeder
{
    public function run(): void
    {
        $demandas = [
            '2026-07-27' => [
                'Fui até a filial que não existe na Asa Sul; passei na Asa Norte (FL-04-DF); desci para a filial 07, onde subi toda a nossa estrutura e fiz a troca do link de rede',
                'Atraso na demanda: servidor demorou muito para reiniciar e a rede estava com informações incorretas',
                'Eu e o Halley testando e decidindo se trocaríamos o servidor de lugar',
            ],
            '2026-07-28' => [
                'Feitos os acessos para os gerentes das filiais 6 e 8, além da criação dos usuários do alarme',
                'Recolocar o DVR para dar imagem na conveniência',
                'Troca de rede das filiais 01 e 03, acompanhando todo o processo de resolução do erro na filial (ONU e roteador precisaram ser trocados)',
                'Instalação da impressora de cupom não fiscal e configuração de novo caixa',
                'Observação: todas as filiais tiveram troca do IP do servidor para 250, sendo necessário falar com o Vetor — o que atrasa o trabalho',
            ],
            '2026-07-29' => [
                'Troca das internets das filiais 08 e 09 (com compra de switch)',
                'Resolução do erro de consulta na Mel do Sol',
                'Resolução da lentidão no computador da Meire',
                'Resolução do erro de rede na filial 15',
            ],
            '2026-07-30' => [
                'PC da Tina: descoberto o problema (SSD) — reinstalação do Windows e dos programas',
                'Alinhamento com o pessoal do sistema para a instalação do sistema no computador dela',
                'DVR da conveniência do Posto Jardim Brasília: refazer e resetar os usuários; avisar ao Halley que o Digisat não estava online',
                'Criação do usuário do ISIC para a gerente da filial 17',
                'PC da Thaís: verificado e recomendada mais uma RAM; resolvida a questão do UltraVNC no PC da Alice e alinhado com o pessoal deles a instalação de outro software',
                'Acompanhamento e diagnóstico, junto ao Halley, da impressora da Adji',
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
