# TODO

Remover daqui o que for concluído.

## Próximas tarefas
- [x] Mapeamento usuário↔GLPI **CONCLUÍDO** (2026-06-22) e `GLPI_DRIVER=api` ligado. Atores: `create`→`_users_id_requester`, `update`→`_users_id_assign`, `find`→resolve por `Ticket_User` (type 1/2) com mapa `id→realname` em cache, `all`→filtra por `/search/Ticket` (campo 4/5). Controllers passam `requester_glpi_id`/`technician_glpi_id`. Validado: cliente 3 / técnico 1 / gestor 5 + 403. Ver CHANGELOG.
- [x] **Agenda — Fase 1 CONCLUÍDA** (2026-06-23): calendário `/agenda` (técnico/gestor), FullCalendar, eventos = TicketTask (móveis, salvam no GLPI) + prazo SLA (fixo), filtro por técnico, arrastar/remarcar. Padrão de repositório (Fake+Api). Ver CHANGELOG.
- [x] **SLA (prazo) nos chamados CONCLUÍDO** (2026-06-23): campo na abertura + "Salvar prazo" no detalhe (técnico/gestor) → grava `time_to_resolve` no GLPI e alimenta a camada de SLA da Agenda. Ver CHANGELOG.
- [ ] Testes de contrato (Fake e Api passam nos mesmos testes) — automatizar o que foi validado à mão (chamados + agenda).

## Agenda — próximas fases
- [ ] **Fase 2 — Google Agenda:** cada técnico conecta a conta Google (OAuth), criar/atualizar/concluir eventos junto com os chamados.
- [ ] **Fase 3 — avançado:** conflito de horário, tempo de deslocamento (Google Maps — custo), notificações, confirmação de presença, sync bidirecional (avaliar se compensa — é o mais arriscado de manter).
- [x] Agenda: **criar/agendar tarefa direto pela tela CONCLUÍDO** (2026-06-23, modal "Novo agendamento" → cria TicketTask no GLPI) + repaginada visual dos eventos. Ver CHANGELOG.
- [ ] Agenda: permissão fina (técnico só remarca/edita as próprias tarefas); editar/excluir tarefa existente pela tela.

## Multi-tenancy por Entidades do GLPI (planejado — entidades reais de produção chegam em breve)
- [ ] Coluna `users.glpi_entity_id` (+ guardar perfil/glpi_id já existentes); NUNCA replicar ativos — sempre consultar o GLPI.
- [x] Estrutura de produção recriada no GLPI local (47 entidades + perfis Técnico FL/Gestor-Clientes + 40 usuários) e **isolamento por entidade validado** por login de usuário (2026-06-23). Ver CHANGELOG.
- [x] **Login do portal SÓ pelo GLPI CONCLUÍDO** (2026-06-23): `authenticateUsing` valida no GLPI, papel vem do perfil, repos Ticket/Planning usam o token do usuário, logout killSession. Isolamento por entidade validado E2E. Contas demo deixaram de autenticar. Ver CHANGELOG.
- [ ] Camada de autorização: no login resolver entity_id + entidades permitidas (entidade + descendentes se recursivo) e guardar na sessão.
- [ ] Filtro automático em TODA consulta GLPI (chamados + inventário: Computer/Monitor/Printer/NetworkEquipment/Phone/Server + documentos). Recomendado: `POST /changeActiveEntities` por request (1 ponto de controle) — alternativa: critério `entities_id` por busca, ou sessão GLPI por usuário.
- [ ] Gestor: seletor Empresa/Filial (Todas|filial); Cliente: sem seletor (só a sua entidade).
- [x] Módulo Inventário real (lê ativos do GLPI por entidade) CONCLUÍDO (2026-06-23) — usa conta de serviço + `changeActiveEntities` (Self-Service não tem direito de ativo); isolamento validado. Ver CHANGELOG.
- [ ] Futuro: cadastro empresa→filiais→responsáveis→usuários criando entidade+usuário+permissões automaticamente.
- [ ] DECISÃO pendente: forma de aplicar o filtro (changeActiveEntities x critério x sessão por usuário).

## Segurança — ANTES de subir ao servidor (não fazer agora; ao finalizar o teste)
- [ ] Conta de serviço restrita: trocar `glpi/glpi` super-admin por usuário de API com perfil limitado a CLIENTES + senha forte; proteger `.env`.
- [ ] Lista branca anti-escalonamento no `DirectoryController` (gestor só atribui Self-Service/Técnico FL/Gestor-Clientes e dentro de CLIENTES) — ou usar token do gestor.
- [ ] Rate limiting além do login (ações de escrita/criação) + throttle global.
- [ ] Revisar validação de TODOS os endpoints de escrita; confirmar ações sensíveis (CSRF e escape já ok).
- [ ] Não expor APIs: GLPI (apirest.php:8080) e banco só acessíveis pelo servidor do portal (firewall/rede interna).
- [ ] HTTPS + cookies secure/httponly; `APP_ENV=production`, `APP_DEBUG=false`.
- [ ] Trocar credenciais padrão do GLPI; reativar `use_password_security`; senhas fortes únicas (não fl2026/Fourline*2026).
- [ ] Rodar `/security-review`; headers de segurança; logs/auditoria; backups. (Detalhes na memória [[portal-itsm-seguranca]].)

## Melhorias
- [ ] Multi-cliente: isolamento por Entity do GLPI (ver seção acima).
- [ ] Estratégia de autenticação dos clientes (GLPI / LDAP / sync).
- [ ] Anexos nos chamados (Documents do GLPI).
- [x] Clientes, Técnicos e Relatórios implementados (leem do GLPI) — 2026-06-23. Ver CHANGELOG.
- [x] Dashboards = Monitoramento (Zabbix) CONCLUÍDO (2026-06-23): Zabbix 7.0 local + Agent 2 (notebook-mateus) + aba lendo a API. Em prod apontar p/ 192.168.101.26. Ver [[zabbix-monitoramento]] / CHANGELOG.
- [ ] Implementar os módulos ainda em placeholder (Base de conhecimento, Automações). [Inventário e Dashboards já feitos]
- [ ] Monitoramento multi-tenant: host groups por cliente/entidade no Zabbix + filtrar dashboard pela entidade do usuário.
- [ ] Dashboard com gráficos.
- [ ] Reset de senha (reativar feature no Fortify).
- [ ] Robustez: cache de token, retry/timeout/circuit breaker, filas, paginação, observabilidade.

## Débitos técnicos
- [ ] `git init` + commit do projeto; rodar `/security-review` antes de produção.
- [ ] `logo-fourline-white.png` tem o ícone vazado na cor exata da navbar (só serve nessa navbar verde).
- [ ] Categorias são lista fixa no `TicketController` (virão do GLPI na Fase 2).
- [ ] `ApiGlpiTicketRepository`: `technicianName` aproximado (`users_id_lastupdater`); App-Token rejeitado quando enviado (usando sem token); falta `killSession` + cache do session token + retry/timeout/circuit breaker.

## Bugs conhecidos
- Nenhum no momento.
