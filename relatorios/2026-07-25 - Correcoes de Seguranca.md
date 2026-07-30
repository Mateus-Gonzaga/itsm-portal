# Relatório de Segurança — FOURLINE Connect

**Data:** 25/07/2026
**Escopo:** Correções aplicadas no código após a análise de cibersegurança do portal.

---

## 1. Resumo

Foram aplicadas as correções de **código** priorizadas na análise. As ações de
**infraestrutura** (senhas, conta de serviço, backups, antivírus) estão descritas
na seção 5 como *runbook* para execução no servidor — elas **não** são feitas pelo
portal e exigem acesso ao GLPI/VM.

---

## 2. Correções aplicadas (código)

### 2.1 Rate limiting (anti-abuso / força-bruta / flood)
Adicionado `throttle` (limite de requisições por minuto por usuário/IP) nos
endpoints de escrita:

| Endpoint | Limite |
|---|---|
| Comentários em chamado (`tickets.comments.store`) | 30/min |
| Upload de anexo em chamado (`tickets.attachments.store`) | 20/min |
| Abrir chamado (cliente/técnico) | 30/min |
| Ações do atendimento (técnico/gestor) | 120/min |
| Aprovar/reabrir chamado (cliente) | 30/min |
| Diretório — criar/editar usuário e entidade (gestor) | 30/min |
| Inventário — mover/definir valor (gestor) | 60/min |
| Base de conhecimento (gestor) | 60/min |

Efeito: um cliente/técnico comprometido não consegue inundar a API nem tentar
força-bruta em massa; retorna HTTP 429 ao estourar.

### 2.2 Endurecimento de uploads
- **Chamados** (`TicketController`): validação trocada de `mimes:` (checa só a
  extensão) para `mimetypes:` (checa o **tipo real** do arquivo). Permitido:
  JPEG, PNG, GIF, WebP e PDF. Máx. 8 MB.
- **Base de conhecimento** (`KnowledgeController::saveFiles`): removidos os tipos
  perigosos `doc/docx/xls/xlsx` (podem conter macros). Agora só PDF + imagens,
  via `mimetypes:`. Máx. 15 MB. Campo de upload da tela ajustado (`accept=".pdf,image/*"`).

Efeito: reduz a superfície de upload de arquivos executáveis/office com macro.
O antivírus (ClamAV) continua recomendado na infraestrutura (seção 5).

### 2.3 Trilha de auditoria
- Nova tabela `audit_logs` (migração `2026_07_25_000400`) + modelo `AuditLog`
  com helper `AuditLog::record($acao, $descricao)` (nunca quebra a ação principal).
- Registra usuário, ação, descrição legível, IP e data/hora nas ações sensíveis:
  - Diretório: criar/editar/ativar-desativar/isolar usuário; criar/editar entidade
    (marca inclusive **troca de senha** e **perfil/entidade** atribuídos).
  - Inventário: mover ativo, definir/remover valor.
  - Base de conhecimento: exclusão de registro.
- Nova tela **Auditoria** (menu do gestor, `/auditoria`) — somente leitura, com
  busca e paginação.

Efeito: rastreabilidade de quem fez o quê (isolamento, mudança de perfil, valores,
exclusões), essencial para investigar incidentes.

---

## 3. Já validados (defesas existentes, confirmadas)
- Sem saída bruta de dados do usuário (`{!! !!}`) — Blade escapa tudo por padrão.
- CSRF ativo em todos os formulários; `$fillable` nos models.
- Isolamento por entidade: cliente forçado a **não-recursivo** no login;
  inventário *fail-closed* quando a entidade é a raiz e não-recursiva.
- Anexos: verificação de propriedade antes de servir (proxy inline).
- `.env` está no `.gitignore` (segredos fora do Git).

---

## 4. Deploy destas mudanças (na VM)

No servidor de produção:

```bash
cd ~/itsm-portal
git pull
docker compose exec app php artisan migrate --force
docker compose exec app php artisan optimize:clear
```

> A migração cria a tabela `audit_logs`. Nenhuma mudança destrutiva.

---

## 5. Runbook de infraestrutura (a executar no servidor — pendente)

> Estas ações **não** passam pelo portal. Requerem acesso ao GLPI e à VM.
> **Importante:** as senhas devem ser digitadas por você — eu não insiro senhas/tokens.

1. **Conta de serviço restrita (GLPI):** criar/usar um usuário de API com um
   perfil que tenha apenas os direitos necessários (ler/escrever chamados,
   inventário, entidades, Infocom). **Não** usar `glpi`/Super-Admin como conta da API.
2. **Trocar credenciais padrão:**
   - Admin do GLPI (`glpi/glpi`) → senha forte.
   - Admin do Zabbix e usuário `portal_api` do Zabbix → senhas fortes.
   - Reativar `use_password_security` no GLPI (política de senha).
3. **`APP_DEBUG=false` / `APP_ENV=production`** confirmados no `.env` da VM
   (o `.env.prod.example` já vem assim).
4. **`.env` com permissão restrita:** `chmod 600 .env` (só o dono lê).
5. **Backups criptografados:** dump diário do PostgreSQL + `storage/app`
   (onde ficam anexos da KB), guardados cifrados e fora da VM.
6. **Antivírus no upload (ClamAV):** varrer arquivos enviados antes de aceitar
   (complementa a restrição de tipos já aplicada no código).
7. **HTTPS** no acesso externo (`suporte.fourline.com.br`).

---

## 6. Pendências relacionadas (fora deste relatório)
- Feature **Mapa de clientes**: rota criada, **view ainda não existe** (quebra se acessada). Concluir ou remover a rota.
- GLPI: conceder à conta de serviço a **leitura** de `PluginGenericobjectDvr`/`Alarme`
  e a **escrita de Infocom** (para o valor do ativo sincronizar).
