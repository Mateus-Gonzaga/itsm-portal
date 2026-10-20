# Auditoria de Segurança — Portal FOURLINE Connect

**Data:** 20/10/2026 · **Escopo:** portal (código, configuração Docker/nginx e comportamento no ar em `suporte.fourline.com.br:8083`), integrações GLPI/Zabbix. **Fora do escopo:** falta de HTTPS (já conhecida).
**Método:** testes externos passivos (só leitura, nada alterado) + revisão de código e configuração.

---

## Resumo

O **código do portal está bem protegido** contra as falhas mais comuns (acesso indevido entre clientes, injeção, XSS, CSRF, força bruta no login). **Os riscos que restam estão nas credenciais e na configuração ao redor** — principalmente a conta de serviço do GLPI com poder total, senhas que circularam em print e a ausência de backup.

| Nível | Qtd. |
|---|---|
| 🔴 Crítico / Alto | 5 |
| 🟠 Médio | 4 |
| 🟢 Baixo | 4 |
| ✅ Correto | 14 controles verificados |

---

## 🔴 Crítico / Alto — agir primeiro

| # | Achado | Risco | Correção |
|---|---|---|---|
| A1 | **Portal usa o usuário `glpi` (super-admin) na API do GLPI** | Quem obtiver o `.env` (ou uma falha futura no portal) controla o GLPI inteiro: apagar chamados, criar admins, ler tudo | Criar conta de serviço própria, perfil restrito à entidade CLIENTES (ler/criar chamados, ler/editar ativos e usuários), senha forte; trocar `GLPI_API_USER/PASSWORD` |
| A2 | **Senhas reais apareceram em print** (GLPI, Zabbix, SMTP, App-Token) | Podem estar em históricos/backups de conversa | Trocar: senha do usuário GLPI usado na API, `portal_api` do Zabbix, caixa `contato@fourline.com.br`, regenerar App-Token |
| A3 | **`APP_DEBUG` estava `true` em produção** (tela de erro mostrou código) — confirmar se já foi desligado | Exibe código-fonte, caminhos e variáveis a qualquer visitante quando ocorre erro | `.env`: `APP_ENV=production`, `APP_DEBUG=false` → `bash deploy.sh` |
| A4 | **`portal_api` do Zabbix ainda Super admin** (se não foi revertido) | O `.env` do portal passa a valer como administrador total do Zabbix | Papel **Admin** com escrita só no grupo "Portal FOURLINE" |
| A5 | **Sem backup** do banco do portal e do GLPI | Perda total em falha de disco/VM ou ataque (ransomware) | Backup diário automático (pg_dump/mysqldump) com cópia fora da VM e teste de restauração |

## 🟠 Médio

| # | Achado | Correção |
|---|---|---|
| M1 | **Chave da Google Agenda** criptografada no Git com a senha escrita no `deploy.sh` (`-k "fourline2026"`) — quem acessa o repositório descriptografa a chave | Mover a senha para o `.env` da VM (ou copiar o `.json` manualmente para a VM e remover o `.enc` do Git); depois **revogar e gerar nova chave** no Google Cloud |
| M2 | **Sem login em dois fatores** para gestores (estrutura do Fortify existe, desligada) | Ativar 2FA (TOTP) obrigatório para o perfil gestor |
| M3 | **Sem Content-Security-Policy** (só os demais cabeçalhos) | Adicionar CSP com as origens usadas (jsdelivr, Google Fonts) — após o HTTPS |
| M4 | **Anexos vindos direto do GLPI (HTML/SVG) abriam "dentro" do portal** → possível XSS armazenado | ✅ **Corrigido nesta auditoria**: só imagem e PDF abrem na tela; o resto vira download isolado (`attachment` + `CSP sandbox` + `nosniff`) |

## 🟢 Baixo

| # | Achado | Correção |
|---|---|---|
| B1 | Limite de login é por usuário+IP (5/min) — tentativas distribuídas em vários IPs não são barradas | Somar limite global por usuário (ex.: 20/h) e alerta de muitas falhas |
| B2 | `agente.sh` servido em HTTP para `curl … \| sudo sh` (risco de interceptação dentro da rede) | Após o HTTPS, servir por HTTPS; conteúdo não tem senha |
| B3 | Redis/PostgreSQL sem exposição externa, mas Redis sem senha | Aceitável (rede interna do Docker); opcional `requirepass` |
| B4 | Dependências não auditadas automaticamente | Rodar `docker compose -f docker-compose.prod.yml exec app composer audit` mensalmente |

---

## ✅ Controles verificados e corretos

1. **Isolamento entre clientes:** chamados lidos com o token do próprio usuário no GLPI; inventário com sessão escopada por entidade (*fail-closed* se a entidade for inválida).
2. **Anexos de chamado:** só do próprio chamado e só se o anexo pertencer a ele (sem leitura de documento alheio por ID).
3. **Papéis:** cliente/técnico/gestor definidos pelo perfil do GLPI no login; nenhum dado do formulário altera o papel.
4. **Rotas sensíveis** (relatórios, diretório, inventário, exportações, Zabbix) restritas a gestor/equipe; testes automáticos cobrem 403.
5. **Anti-escalonamento:** gestor não consegue atribuir perfis de admin nem entidades fora de CLIENTES (teste automático).
6. **Força bruta no login:** 5 tentativas/min por usuário+IP.
7. **CSRF** ativo em todos os formulários (sem exceções).
8. **XSS:** saídas escapadas; os 3 usos de HTML cru são estáticos ou já escapados.
9. **Mass assignment:** todos os models com `$fillable`; nenhum `request->all()`.
10. **Uploads:** só imagens/PDF, até 8 MB (chamado) e 15 MB (base de conhecimento); arquivos fora da pasta pública.
11. **Arquivos sensíveis bloqueados** no servidor: `.env`, `.git`, `composer`, logs, `storage` (403/404).
12. **Cabeçalhos:** versão do nginx/PHP ocultas; `X-Frame-Options`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`; cookie de sessão `HttpOnly` + `SameSite=Lax`.
13. **Exposição de rede:** só a porta 8083 publicada; banco e Redis apenas na rede interna do Docker.
14. **Rastreabilidade:** auditoria de ações (criar/editar/mover/excluir), aviso de erro por e-mail e monitoramento no Zabbix (portal, GLPI, fila de e-mails, banco, cache).

---

## Plano recomendado

1. **Hoje:** confirmar `APP_DEBUG=false` (A3); rebaixar `portal_api` (A4).
2. **Esta semana:** conta de serviço restrita no GLPI (A1) e troca das senhas expostas (A2); backup diário (A5).
3. **Depois do HTTPS:** cookies `Secure`, CSP (M3), `agente.sh` por HTTPS (B2).
4. **Próximas semanas:** 2FA para gestores (M2), chave do Google fora do Git (M1), `composer audit` mensal (B4).
