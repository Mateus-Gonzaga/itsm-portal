<?php

namespace App\Console\Commands;

use App\Enums\TicketPriority;
use App\Enums\TicketType;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CriarDemandasChamados extends Command
{
    protected $signature = 'tickets:criar-demandas
                            {--dry-run : Apenas simula a criação sem gravar no GLPI}
                            {--force : Cria mesmo se já existir chamado com título idêntico}';

    protected $description = 'Cria os chamados para cada demanda do relatório de pendências da equipe no respectivo cliente';

    public function handle(
        GlpiTicketRepositoryInterface $tickets,
        GlpiDirectoryRepositoryInterface $dir
    ): int {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');

        $this->info($dryRun ? '=== SIMULAÇÃO DE CRIAÇÃO DE CHAMADOS (--dry-run) ===' : '=== CRIANDO CHAMADOS DAS DEMANDAS NO GLPI ===');

        $users = $dir->users();
        $entities = $dir->entities();
        $existingTickets = $tickets->all();

        $demandas = $this->listaDemandas();
        $this->info("Total de demandas a processar: " . count($demandas));

        $criados = 0;
        $pulados = 0;
        $erros = 0;

        $tabela = [];

        foreach ($demandas as $d) {
            $clienteInfo = $this->resolverCliente($d['aliases'], $d['cliente_nome'], $users, $entities);

            // Verifica duplicidade se não tiver flag --force
            $jaExiste = $existingTickets->first(function ($t) use ($d, $clienteInfo) {
                return mb_strtolower(trim($t->title)) === mb_strtolower(trim($d['title']))
                    || (
                        str_contains(mb_strtolower($t->title), mb_strtolower(mb_substr($d['title'], 0, 25)))
                        && (
                            ($clienteInfo['user_id'] && $t->requesterGlpiId === $clienteInfo['user_id'])
                            || (str_contains(mb_strtolower($t->entity), mb_strtolower($clienteInfo['entity_name'] ?? '')))
                        )
                    );
            });

            if ($jaExiste && ! $force) {
                $this->warn("⚠️  [JÁ EXISTE] {$d['cliente_nome']} — #{$jaExiste->id}: {$d['title']}");
                $pulados++;
                continue;
            }

            $tabela[] = [
                'Cliente' => $clienteInfo['user_name'] ?? $d['cliente_nome'],
                'Entidade' => Str::limit($clienteInfo['entity_name'] ?? '—', 35),
                'Título' => Str::limit($d['title'], 40),
                'Prioridade' => $d['priority'],
                'Categoria' => $d['category'],
            ];

            if ($dryRun) {
                $criados++;
                continue;
            }

            try {
                $payload = [
                    'title' => $d['title'],
                    'description' => $d['description'],
                    'priority' => $d['priority'],
                    'type' => $d['type'],
                    'category' => $d['category'],
                    'entity' => $clienteInfo['entity_name'] ?? $d['cliente_nome'],
                ];

                if (! empty($clienteInfo['user_id'])) {
                    $payload['requester_glpi_id'] = $clienteInfo['user_id'];
                    $payload['requester'] = $clienteInfo['user_name'];
                } else {
                    $payload['requester'] = $d['cliente_nome'];
                }

                if (! empty($clienteInfo['entity_id'])) {
                    $payload['entity_id'] = $clienteInfo['entity_id'];
                }

                $novo = $tickets->create($payload);
                $this->info("✅ [CRIADO #{$novo->id}] {$d['cliente_nome']} — {$d['title']}");
                $criados++;
            } catch (\Throwable $e) {
                $this->error("❌ [ERRO] {$d['cliente_nome']} — {$d['title']}: " . $e->getMessage());
                $erros++;
            }
        }

        if ($dryRun) {
            $this->table(['Cliente', 'Entidade', 'Título', 'Prioridade', 'Categoria'], $tabela);
            $this->info("\nSimulação concluída: {$criados} chamado(s) prontos para criar.");
        } else {
            $this->info("\nProcessamento concluído: {$criados} criado(s), {$pulados} pulado(s) por já existirem, {$erros} erro(s).");
        }

        return $erros > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Localiza o usuário ou entidade correspondente ao cliente no GLPI.
     */
    private function resolverCliente(array $aliases, string $defaultName, $users, $entities): array
    {
        // 1) Busca direta por usuário
        foreach ($aliases as $alias) {
            $needle = mb_strtolower(trim($alias));
            $foundUser = $users->first(function ($u) use ($needle) {
                $name = mb_strtolower($u['name'] ?? '');
                $login = mb_strtolower($u['login'] ?? '');
                $entity = mb_strtolower($u['entity'] ?? '');

                return str_contains($name, $needle) || str_contains($login, $needle) || str_contains($entity, $needle);
            });

            if ($foundUser) {
                return [
                    'user_id' => (int) $foundUser['id'],
                    'user_name' => $foundUser['name'],
                    'entity_id' => (int) ($foundUser['entity_id'] ?? 0) ?: null,
                    'entity_name' => $foundUser['entity'] ?? null,
                ];
            }
        }

        // 2) Busca por entidade
        foreach ($aliases as $alias) {
            $needle = mb_strtolower(trim($alias));
            $foundEntity = $entities->first(function ($e) use ($needle) {
                $name = mb_strtolower($e['name'] ?? '');
                $comp = mb_strtolower($e['completename'] ?? '');

                return str_contains($name, $needle) || str_contains($comp, $needle);
            });

            if ($foundEntity) {
                return [
                    'user_id' => null,
                    'user_name' => $defaultName,
                    'entity_id' => (int) $foundEntity['id'],
                    'entity_name' => $foundEntity['completename'] ?? $foundEntity['name'],
                ];
            }
        }

        // 3) Fallback para entidade CLIENTES se houver
        $clientesRoot = $entities->first(fn ($e) => ($e['name'] ?? '') === 'CLIENTES');

        return [
            'user_id' => null,
            'user_name' => $defaultName,
            'entity_id' => $clientesRoot ? (int) $clientesRoot['id'] : null,
            'entity_name' => $defaultName,
        ];
    }

    /**
     * Catálogo completo das 47 demandas extraídas do relatório + demanda extra do usuário.
     */
    private function listaDemandas(): array
    {
        return [
            // 1. A2 Arquitetura / A2 Projetos
            [
                'cliente_nome' => 'A2 Arquitetura',
                'aliases' => ['a2 projetos', 'a2projetos', 'a2 arquitetura', 'a2'],
                'title' => 'Troca de memória RAM - Computador da Taís',
                'description' => "Cliente: A2 Arquitetura\nDemanda: Efetuar a troca da memória do computador da Taís.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'A2 Arquitetura',
                'aliases' => ['a2 projetos', 'a2projetos', 'a2 arquitetura', 'a2'],
                'title' => 'Migração de versão do sistema on-premise para Web',
                'description' => "Cliente: A2 Arquitetura\nDemanda: Trocar versão do sistema onpremise para Web.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 2. Águia da Lavoura
            [
                'cliente_nome' => 'Águia da Lavoura',
                'aliases' => ['aguia da lavoura', 'águia da lavoura', 'aguia'],
                'title' => 'Configuração de venda fracionada de ração no Digisat',
                'description' => "Cliente: Águia da Lavoura\nDemanda: Fazer a venda fracionada do pacote de ração no sistema Digisat.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 3. Apoio Distribuidora ADAL
            [
                'cliente_nome' => 'Apoio Distribuidora ADAL',
                'aliases' => ['adal', 'apoio distribuidora', 'apoio'],
                'title' => 'Levantamento de Infraestrutura de TI',
                'description' => "Cliente: Apoio Distribuidora ADAL\nDemanda: Realizar o levantamento de infraestrutura de TI da unidade ADAL.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Apoio Distribuidora ADAL',
                'aliases' => ['adal', 'apoio distribuidora', 'apoio'],
                'title' => 'Organização e cabeamento do Rack',
                'description' => "Cliente: Apoio Distribuidora ADAL\nDemanda: Efetuar organização e padronização do rack.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Apoio Distribuidora ADAL',
                'aliases' => ['adal', 'apoio distribuidora', 'apoio'],
                'title' => 'Troca de Sistema Operacional do Servidor',
                'description' => "Cliente: Apoio Distribuidora ADAL\nDemanda: Trocar sistema operacional do servidor.",
                'category' => 'Sistemas',
                'priority' => 'high',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Apoio Distribuidora ADAL',
                'aliases' => ['adal', 'apoio distribuidora', 'apoio'],
                'title' => 'Implementação de link de contingência e failover',
                'description' => "Cliente: Apoio Distribuidora ADAL\nDemanda: Criar failover de rede/servidor para alta disponibilidade.",
                'category' => 'Redes',
                'priority' => 'high',
                'type' => 'request',
            ],

            // 4. Auto Posto Jardim Brasília
            [
                'cliente_nome' => 'Auto Posto Jardim Brasília',
                'aliases' => ['jardim', 'auto posto jardim', 'jardim brasilia', 'jardim brasília'],
                'title' => 'Substituição do Rack de TI',
                'description' => "Cliente: Auto Posto Jardim Brasília\nDemanda: Trocar o rack.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Auto Posto Jardim Brasília',
                'aliases' => ['jardim', 'auto posto jardim', 'jardim brasilia', 'jardim brasília'],
                'title' => 'Organização geral da infraestrutura de TI',
                'description' => "Cliente: Auto Posto Jardim Brasília\nDemanda: Organizar a infraestrutura de rede e equipamentos.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Auto Posto Jardim Brasília',
                'aliases' => ['jardim', 'auto posto jardim', 'jardim brasilia', 'jardim brasília'],
                'title' => 'Troca do teclado da pista',
                'description' => "Cliente: Auto Posto Jardim Brasília\nDemanda: Trocar teclado da pista de abastecimento.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'incident',
            ],

            // 5. Auto Posto Morais
            [
                'cliente_nome' => 'Auto Posto Morais',
                'aliases' => ['postomorais', 'morais', 'auto posto morais'],
                'title' => 'Verificação e reparo de câmeras inoperantes',
                'description' => "Cliente: Auto Posto Morais\nDemanda: Verificar câmeras que estão sem funcionar.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'incident',
            ],
            [
                'cliente_nome' => 'Auto Posto Morais',
                'aliases' => ['postomorais', 'morais', 'auto posto morais'],
                'title' => 'Instalação de Nobreak com bateria nova',
                'description' => "Cliente: Auto Posto Morais\nDemanda: Levar e instalar nobreak com bateria nova.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 6. Auto Posto Rpaz (Rainha da Paz)
            [
                'cliente_nome' => 'Auto Posto Rainha da Paz',
                'aliases' => ['rpaz', 'rainha da paz', 'auto posto rainha da paz', 'auto posto rpaz'],
                'title' => 'Orçamento de sistema de automação (Águia)',
                'description' => "Cliente: Auto Posto Rpaz\nDemanda: Orçamento de sistema de automação (Águia).",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Auto Posto Rainha da Paz',
                'aliases' => ['rpaz', 'rainha da paz', 'auto posto rainha da paz', 'auto posto rpaz'],
                'title' => 'Renovação da infraestrutura de TI',
                'description' => "Cliente: Auto Posto Rpaz\nDemanda: Renovar infraestrutura de tecnologia.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 7. Avelar Comercial
            [
                'cliente_nome' => 'Avelar Comercial',
                'aliases' => ['avelarautomotiva', 'avelar comercial', 'avelar'],
                'title' => 'Configuração de acesso remoto ao servidor via VNC',
                'description' => "Cliente: Avelar Comercial\nDemanda: Criar acesso ao servidor via VNC.",
                'category' => 'Redes',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Avelar Comercial',
                'aliases' => ['avelarautomotiva', 'avelar comercial', 'avelar'],
                'title' => 'Liberação de acesso ao Digisat via Força de Vendas Mobile',
                'description' => "Cliente: Avelar Comercial\nDemanda: Criar acesso do Digisat pelo força de vendas Mobile.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 8. Bionate Farmácia (Salicis)
            [
                'cliente_nome' => 'Bionate Farmácia (Salicis)',
                'aliases' => ['salicis', 'bionate farmacia', 'bionate farmácia', 'bionate'],
                'title' => 'Apresentação e alinhamento do sistema RHID',
                'description' => "Cliente: Bionate Farmácia (Salicis)\nDemanda: Mostrar sistema RHID.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Bionate Farmácia (Salicis)',
                'aliases' => ['salicis', 'bionate farmacia', 'bionate farmácia', 'bionate'],
                'title' => 'Substituição/recriação de VM Windows no servidor',
                'description' => "Cliente: Bionate Farmácia (Salicis)\nDemanda: Trocar VM do Windows no servidor.",
                'category' => 'Sistemas',
                'priority' => 'high',
                'type' => 'request',
            ],

            // 9. Drogacei
            [
                'cliente_nome' => 'Drogacei',
                'aliases' => ['drogacei'],
                'title' => 'Levantamento de inventário e pendências - 17 Filiais',
                'description' => "Cliente: Drogacei\nDemanda: Fazer levantamento de inventário e lista de pendência das 17 Filiais.",
                'category' => 'Hardware',
                'priority' => 'high',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Drogacei',
                'aliases' => ['drogacei'],
                'title' => 'Auditoria de conformidade de inventário das filiais',
                'description' => "Cliente: Drogacei\nDemanda: Ver qual filial já está completa.",
                'category' => 'Outros',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Drogacei',
                'aliases' => ['drogacei'],
                'title' => 'Levantamento de pendências de CFTV e Alarmes',
                'description' => "Cliente: Drogacei\nDemanda: Fazer lista de pendências de CFTV e Alarme.",
                'category' => 'Hardware',
                'priority' => 'high',
                'type' => 'request',
            ],

            // 10. Confecções Janete
            [
                'cliente_nome' => 'Confecções Janete',
                'aliases' => ['janete', 'confeccoes janete', 'confecções janete'],
                'title' => 'Atualização de versão do sistema Digisat',
                'description' => "Cliente: Confecções Janete\nDemanda: Atualizar o Digisat.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 11. Conveniência Jardim Brasília
            [
                'cliente_nome' => 'Conveniência Jardim Brasília',
                'aliases' => ['convenienciajardim', 'conveniencia jardim', 'conveniência jardim'],
                'title' => 'Verificação técnica do monitor do PDV',
                'description' => "Cliente: Conveniência Jardim Brasília\nDemanda: Verificar situação do Monitor.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'incident',
            ],

            // 12. Conveniência RK
            [
                'cliente_nome' => 'Conveniência RK',
                'aliases' => ['conveniencia rk', 'conveniência rk', 'rk'],
                'title' => 'Desenho e implantação do fluxo de lançamento de notas',
                'description' => "Cliente: Conveniência RK\nDemanda: Criar Fluxo dos lançamentos das Notas.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 13. Boutique do Prazer
            [
                'cliente_nome' => 'Boutique do Prazer',
                'aliases' => ['boutique', 'boutique do prazer'],
                'title' => 'Atualização de versão do sistema Digisat',
                'description' => "Cliente: Boutique do Prazer\nDemanda: Atualizar Digisat.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 14. Drogaria Fatima (Davi Vaz - Fátima)
            [
                'cliente_nome' => 'Drogaria Fatima',
                'aliases' => ['fatima', 'fátima', 'davi vaz', 'drogaria fatima', 'drogaria fátima'],
                'title' => 'Levantamento de inventário - 7 Filiais',
                'description' => "Cliente: Drogaria Fatima\nDemanda: Levantamento de Inventário das 7 Filiais.",
                'category' => 'Hardware',
                'priority' => 'high',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Drogaria Fatima',
                'aliases' => ['fatima', 'fátima', 'davi vaz', 'drogaria fatima', 'drogaria fátima'],
                'title' => 'Diagnóstico e checagem de pendências de TI das lojas',
                'description' => "Cliente: Drogaria Fatima\nDemanda: Checar pendências das lojas.",
                'category' => 'Outros',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Drogaria Fatima',
                'aliases' => ['fatima', 'fátima', 'davi vaz', 'drogaria fatima', 'drogaria fátima'],
                'title' => 'Demonstração e orientação do sistema de ponto RHID',
                'description' => "Cliente: Drogaria Fatima\nDemanda: Mostrar o Sistema RHID.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Drogaria Fatima',
                'aliases' => ['fatima', 'fátima', 'davi vaz', 'drogaria fatima', 'drogaria fátima'],
                'title' => 'Manutenção emergencial no DVR da Filial 01',
                'description' => "Cliente: Drogaria Fatima\nDemanda: DVR da filial 01 não está funcionando.",
                'category' => 'Hardware',
                'priority' => 'high',
                'type' => 'incident',
            ],

            // 15. Efycaz Contabilidade
            [
                'cliente_nome' => 'Efycaz Contabilidade',
                'aliases' => ['efycaz contabilidade', 'efycaz'],
                'title' => 'Desenvolvimento de Landing Page institucional',
                'description' => "Cliente: Efycaz Contabilidade\nDemanda: Criar uma Landing Page.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Efycaz Contabilidade',
                'aliases' => ['efycaz contabilidade', 'efycaz'],
                'title' => 'Configuração e gerenciamento de contas de e-mail',
                'description' => "Cliente: Efycaz Contabilidade\nDemanda: Gerenciamento de E-mails.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Efycaz Contabilidade',
                'aliases' => ['efycaz contabilidade', 'efycaz'],
                'title' => 'Implementação de rotina de backup de arquivos',
                'description' => "Cliente: Efycaz Contabilidade\nDemanda: Backup dos arquivos.",
                'category' => 'Sistemas',
                'priority' => 'high',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Efycaz Contabilidade',
                'aliases' => ['efycaz contabilidade', 'efycaz'],
                'title' => 'Estruturação e gestão dos arquivos corporativos',
                'description' => "Cliente: Efycaz Contabilidade\nDemanda: Gerenciamento dos arquivos da empresa.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 16. Entreposto Apícola (Mel do Sol)
            [
                'cliente_nome' => 'Entreposto Apícola (Mel do Sol)',
                'aliases' => ['meldosol', 'mel do sol', 'entreposto apícola', 'entreposto apicola', 'entreposto'],
                'title' => 'Finalização da implantação do sistema Digisat',
                'description' => "Cliente: Entreposto Apícola (Mel do Sol)\nDemanda: Sistema Digisat finalizar implantação.",
                'category' => 'Sistemas',
                'priority' => 'high',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Entreposto Apícola (Mel do Sol)',
                'aliases' => ['meldosol', 'mel do sol', 'entreposto apícola', 'entreposto apicola', 'entreposto'],
                'title' => 'Configuração do BI do Sistema Digisat',
                'description' => "Cliente: Entreposto Apícola (Mel do Sol)\nDemanda: Configurar o BI Sistema da Digisat no Sistema da empresa.",
                'category' => 'Sistemas',
                'priority' => 'high',
                'type' => 'request',
            ],

            // 17. MSV Transportadora
            [
                'cliente_nome' => 'MSV Transportadora',
                'aliases' => ['msv', 'msv transportadora'],
                'title' => 'Acompanhamento operacional do sistema SimplesCT-e',
                'description' => "Cliente: MSV Transportadora\nDemanda: Acompanhar o sistema SimplesCT-e.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 18. Posto Tambaú
            [
                'cliente_nome' => 'Posto Tambaú',
                'aliases' => ['posto tambau', 'posto tambaú', 'tambau', 'tambaú'],
                'title' => 'Acompanhamento operacional do sistema SimplesCT-e',
                'description' => "Cliente: Posto Tambaú\nDemanda: Acompanhar o sistema SimplesCT-e.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 19. Pré Moldados São Bento
            [
                'cliente_nome' => 'Pré Moldados São Bento',
                'aliases' => ['sao bento', 'são bento', 'pre moldados', 'pré moldados'],
                'title' => 'Atendimento de pós-venda e validação de serviços',
                'description' => "Cliente: Pré Moldados São Bento\nDemanda: Efetuar um pos venda.",
                'category' => 'Outros',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 20. Recon Reparo da Construção
            [
                'cliente_nome' => 'Recon Reparo da Construção',
                'aliases' => ['recon', 'recon reparo da construcao', 'recon reparo da construção'],
                'title' => 'Manutenção e reparo de câmeras inoperantes',
                'description' => "Cliente: Recon Reparo da Construção\nDemanda: Consertar as câmeras que não funcionam.",
                'category' => 'Hardware',
                'priority' => 'high',
                'type' => 'incident',
            ],
            [
                'cliente_nome' => 'Recon Reparo da Construção',
                'aliases' => ['recon', 'recon reparo da construcao', 'recon reparo da construção'],
                'title' => 'Auditoria e verificação de backups do sistema',
                'description' => "Cliente: Recon Reparo da Construção\nDemanda: Verificar backups do sistema.",
                'category' => 'Sistemas',
                'priority' => 'high',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Recon Reparo da Construção',
                'aliases' => ['recon', 'recon reparo da construcao', 'recon reparo da construção'],
                'title' => 'Verificação e diagnóstico de infraestrutura de TI',
                'description' => "Cliente: Recon Reparo da Construção\nDemanda: Verificar infraestrutura.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 21. Recplan Pneus
            [
                'cliente_nome' => 'Recplan Pneus',
                'aliases' => ['recplan', 'recplan pneus'],
                'title' => 'Inspeção e manutenção preventiva de infraestrutura',
                'description' => "Cliente: Recplan\nDemanda: Verificação da infraestrutura.",
                'category' => 'Hardware',
                'priority' => 'medium',
                'type' => 'request',
            ],
            [
                'cliente_nome' => 'Recplan Pneus',
                'aliases' => ['recplan', 'recplan pneus'],
                'title' => 'Configuração e estabilização de conexão VPN e MSTSC',
                'description' => "Cliente: Recplan\nDemanda: Conexão VPN e MSTSC.",
                'category' => 'Redes',
                'priority' => 'high',
                'type' => 'request',
            ],

            // 22. Restaurante Chapéu de Couro
            [
                'cliente_nome' => 'Restaurante Chapéu de Couro',
                'aliases' => ['chapeu de couro', 'chapéu de couro', 'restaurante chapeu de couro'],
                'title' => 'Atualização de versão do sistema Digisat',
                'description' => "Cliente: Restaurante Chapéu de Couro\nDemanda: Atualizar o Digisat.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 23. RK Transportes
            [
                'cliente_nome' => 'RK Transportes',
                'aliases' => ['rk transportes', 'rk'],
                'title' => 'Acompanhamento operacional do sistema SimplesCT-e',
                'description' => "Cliente: RK Transportes\nDemanda: Acompanhar o sistema SimplesCT-e.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],

            // 24. Transportadora e Agropecuária Fórmula 1 (URGENTE / PRIORIDADE MÁXIMA)
            [
                'cliente_nome' => 'Transportadora e Agropecuária Fórmula 1',
                'aliases' => ['formula 1', 'fórmula 1', 'transportadora formula 1', 'agropecuaria formula 1'],
                'title' => '[URGENTE] Implantação do sistema Simples CT-e',
                'description' => "Cliente: Transportadora e Agropecuária Fórmula 1\nDemanda: Efetuar a implantação do sistema Simples CT-e.\nObservação: PRIORIDADE MÁXIMA DA EQUIPE.",
                'category' => 'Sistemas',
                'priority' => 'urgent',
                'type' => 'request',
            ],

            // 25. ZM Combustíveis
            [
                'cliente_nome' => 'ZM Combustíveis',
                'aliases' => ['zm combustiveis', 'zm combustíveis', 'zm'],
                'title' => 'Acompanhamento operacional do sistema SimplesCT-e',
                'description' => "Cliente: ZM Combustíveis\nDemanda: Acompanhar o sistema SimplesCT-e.",
                'category' => 'Sistemas',
                'priority' => 'medium',
                'type' => 'request',
            ],
        ];
    }
}
