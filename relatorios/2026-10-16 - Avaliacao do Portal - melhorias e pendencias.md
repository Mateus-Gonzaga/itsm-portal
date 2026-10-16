# Avaliação do Portal FOURLINE Connect — melhorias e pendências

**Data:** 16/10/2026 · **Base:** repositório `itsm-portal` (GitHub, branch `main`, commit `e741f49`, sincronizado)

O que está aqui saiu da leitura do código e do histórico das sessões. Nada foi alterado no portal nem nos servidores.

---

## 1. Situação geral

O portal está **maduro e em produção**. Funcionam: chamados (abertura em nome do cliente, anexos, status, SLA, e-mail), agenda + kanban, inventário completo (criar/editar/mover/excluir, relatório com marca), base de conhecimento, perfil do cliente, diretório (entidades/usuários), técnicos, relatórios, dashboards do Zabbix e auditoria.

O isolamento por cliente está implementado: o cliente só enxerga os próprios chamados (o GLPI filtra pelo token dele) e o inventário da própria entidade (`changeActiveEntities`).

---

## 1.1 Medição do portal no ar (16/10/2026, logado como gestor)

| Página | Tempo | Tamanho |
|---|---|---|
| Login | 0,14 s | — |
| Dashboards (Zabbix) | 0,6 s | 93 KB |
| Clientes | 0,9 s | 207 KB |
| Todos os chamados | 1,0 s | 40 KB |
| Notificações (sino, a cada 60 s) | 1,1 s | 3 KB |
| Painel | 1,4 s | 26 KB |
| Detalhe do chamado | 1,8 s | 30 KB |
| Inventário | 2,1 s | **1,2 MB** |
| Agenda | 2,3 s | 251 KB |
| Base de conhecimento | 2,5 s | 30 KB |
| Relatórios | 2,8 s | 327 KB |
| **Técnicos** | **19–29 s** | 30 KB |

**Técnicos — causa e correção (commit desta data):** buscava os chamados de cada técnico separadamente e lia cada chamado um a um no GLPI. Agora carrega todos os chamados uma vez (atores em lote) e agrupa por técnico.

**Segurança encontrada na medição:** o portal responde **só em HTTP** (sem HTTPS) — login e senha trafegam sem criptografia; cookies sem `Secure`; o servidor expõe versões (`nginx/1.31.2`, `PHP/8.4.25`); sem cabeçalhos de segurança.

## 2. Pendências — fazer primeiro (operação)

| # | Pendência | Por que importa | Onde |
|---|---|---|---|
| 1 | **Fila de e-mails do GLPI (`queuednotification`) estava desativada desde 16/09** | Respostas e soluções não chegam ao cliente. Limpar a fila antiga, reativar e passar para modo **CLI + cron** na VM do GLPI | GLPI → Ações automáticas |
| 2 | **Cadastrar o e-mail dos clientes** nos usuários do GLPI | Chamados abertos direto no GLPI só notificam quem tem e-mail | GLPI → Usuários |
| 3 | **Tipos Nobreak e TV** no GenericObject | Os cards existem no portal; se os tipos não existirem no GLPI, a criação dá erro | GLPI → Objetos genéricos |
| 4 | **Backup** do banco do portal e do GLPI | Hoje não há rotina de backup no deploy | VMs |
| 5 | **Deploy key** no lugar do token pessoal na VM | Hoje o `git pull` depende de credencial pessoal | VM do portal |
| 6 | Arquivos soltos fora do Git: `RelatorioEmpresasAtivos.php` e `Checklist_Inventario_Filiais_Fourline.docx` | Decidir se o comando entra no repositório; o .docx não deve entrar | WSL |

## 3. Segurança (checklist de produção ainda aberto)

| # | Item | Observação |
|---|---|---|
| 1 | Conta de serviço do GLPI com permissão mínima | Confirmar que o portal **não** usa super-admin (`glpi/glpi`) |
| 2 | Credenciais padrão do GLPI trocadas, senhas fortes | glpi, tech, normal, post-only |
| 3 | HTTPS de ponta a ponta + cookies `secure` | Confirmar o certificado em `suporte.fourline.com.br` |
| 4 | Cabeçalhos de segurança no nginx | Não há `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, CSP |
| 5 | GLPI (`apirest.php`) e bancos acessíveis só pela rede interna | Firewall entre VMs |
| 6 | Rodar `/security-review` | Nunca foi rodado |

## 4. Robustez e desempenho (técnico)

| # | Problema | Efeito hoje | Sugestão |
|---|---|---|---|
| 1 | Cada página abre **uma sessão nova no GLPI** (conta de serviço) e não a encerra | Mais lento e acumula sessões no GLPI | Guardar o token em cache por alguns minutos + `killSession` |
| 2 | Inventário faz **11 chamadas ao GLPI** a cada abertura | Tela pesada conforme cresce | Cache curto (1–2 min), limpo ao criar/editar/excluir |
| 3 | Limite de **500 itens por tipo** no inventário | Acima disso some da lista sem aviso | Paginar a busca no GLPI |
| 4 | E-mail enviado **dentro da requisição** | Se o SMTP demorar, a abertura do chamado demora | Mandar para a fila (`QUEUE_CONNECTION=database` já existe) |
| 5 | Sem timeout/retry nas chamadas ao GLPI | GLPI lento = portal travado | `timeout()` + `retry()` no cliente HTTP |
| 6 | **Poucos testes automatizados** (~12) | Mudanças grandes (inventário, e-mail) sem teste | Testes de contrato Fake × Api para inventário e chamados |

## 5. Melhorias de produto sugeridas

**Rápidas (pouco esforço, alto valor)**
- **Remover/unificar os módulos placeholder** "Monitoramento" e "Automações" (o monitoramento real já é a aba Dashboards).
- **Exportar inventário em Excel/CSV** (além do relatório para impressão).
- **Histórico do ativo:** mostrar no detalhe quem criou/editou (já existe na auditoria).
- **Atualizar `docs/TODO.md`** — está desatualizado (lista como pendente coisas já feitas: anexos, base de conhecimento, inventário).

**Médias**
- **Ligar ativo ↔ chamado:** abrir chamado a partir do ativo e ver os chamados de cada equipamento.
- **Alertas de SLA:** aviso (sino/e-mail) para chamados perto de vencer ou vencidos.
- **Pesquisa de satisfação** ao fechar o chamado (nota 1–5 + comentário) → indicador por técnico/cliente nos Relatórios.
- **Dashboard com gráficos:** chamados por cliente, tempo médio de solução, SLA cumprido.
- **E-mails de resposta com a marca do portal** (hoje a abertura sai pelo portal e as respostas pelo modelo do GLPI — padronizar visual e link do portal).

**Maiores (planejadas, aguardando decisão)**
- **Regra de entidade automática** para computadores que chegam pelo GLPI Agent (tag → filial).
- **Google Agenda:** código pronto e desligado; falta o setup no Google.
- **Criar novos tipos de ativo pelo portal** (GenericObject) — adiado; testar primeiro no GLPI de desenvolvimento.
- **Monitoramento por cliente** (host groups do Zabbix por entidade).

---

## 6. Ordem recomendada

1. Fechar o e-mail (fila do GLPI em CLI + e-mails dos clientes) — impacto direto no cliente.
2. Backup + deploy key + itens de segurança 1–4.
3. Cache de sessão/inventário + e-mail em fila (o portal fica visivelmente mais rápido).
4. Melhorias rápidas (placeholders, exportar Excel, TODO).
5. Alertas de SLA, ativo ↔ chamado, satisfação, gráficos.
