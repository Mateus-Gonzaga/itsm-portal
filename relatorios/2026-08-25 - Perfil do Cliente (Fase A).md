# Relatório Final — Perfil do Cliente (Fase A)

**Data:** 25/08/2026 · **Escopo:** implementar o Perfil do Cliente vinculado às Entidades do GLPI por `glpi_entity_id`, Fase A (sem resumo técnico ao vivo do GLPI).
**Decisões aprovadas:** acesso só equipe (gestor/técnico); valor mensal visível a toda a equipe; entrega em Fase A.

---

## 1. Arquivos criados
- `app/Http/Controllers/ClientProfileController.php` — show/update (1×1) + CRUD de contatos, links de internet e contratos; guards de acesso.
- `app/Support/ClientLinks.php` — geração central e segura de links (tel/WhatsApp/Maps/Waze) + validação de CNPJ.
- Models: `ClientProfile`, `ClientContact`, `ClientInternetLink`, `ClientInfrastructure`, `ClientOperation`, `ClientContract`.
- Views: `resources/views/modules/client-profile.blade.php` e `partials/client-profile-modals.blade.php`.
- Migrations: `2026_08_25_000100_create_client_profiles_table` … `..._000150_create_client_contracts_table` (6 tabelas).

## 2. Arquivos alterados
- `routes/web.php` — grupo `role:tecnico,gestor` com show (GET) e escritas (`throttle:60,1`).
- `resources/views/modules/clients.blade.php` — botão **Perfil** nas entidades nível ≥3.
- `docs/CHANGELOG.md`.

## 3. Banco de dados
- `client_profiles` (**`glpi_entity_id` UNIQUE** — vínculo) + identidade + endereço + contato da loja.
- `client_infrastructure` (1×1), `client_operations` (1×1) — FK `unique` cascade.
- `client_contacts`, `client_internet_links`, `client_contracts` (N) — FK cascade.
- ORM Eloquent; relacionamentos hasMany/hasOne/belongsTo; `$fillable` explícito; casts (boolean/date/decimal).

## 4. Integração GLPI
- **Reuso** de `GlpiDirectoryRepositoryInterface::entities()` (conta de serviço) para nome/caminho/hierarquia/validação da entidade. Nenhuma chamada GLPI nova nem duplicada; nenhuma chamada a partir do frontend.
- Dados de identidade/hierarquia = GLPI; ficha operacional/administrativa = banco do portal. Sem segunda fonte de verdade.

## 5. Segurança (mapeada na Etapa 2 → implementada)
- **S1 IDOR (crítico):** `entityOrFail()` valida que o ID é entidade real sob `CLIENTES` (nível ≥3); `guardChild()` garante que contato/link/contrato pertence ao perfil daquela entidade. Nunca confia só na URL. Rotas staff-only.
- **S2 Credenciais:** tokens/segredos permanecem no backend (config/.env); nada exposto na view.
- **S3 Links externos:** helper central `ClientLinks` — telefone normalizado (só dígitos), destinos fixos com `rawurlencode`, `target=_blank rel=noopener`; retorna null quando dado insuficiente (ação não aparece).
- **S4 XSS:** saída via Blade `{{ }}` (escapada); sem `{!! !!}` com dado de usuário; JS usa dataset/textContent.
- **S5 Mass assignment:** `validate()` com allowlist por seção; `glpi_entity_id` definido pelo guard, nunca pelo corpo; `$fillable` restrito.
- **S6 SQLi:** Eloquent parametrizado.
- **S7 Abuso:** `throttle:60,1` nas escritas.
- **S8 Auditoria:** `AuditLog::record` em todas as escritas (update/contato/internet/contrato — create/update/delete).
- **S9 Indisponibilidade GLPI:** guard usa `entities()`; se o GLPI cair, a tela retorna erro controlado (não vaza stack).
- **CNPJ:** validado por dígitos verificadores (`ClientLinks::isValidCnpj`).

## 6. Testes
> **Importante:** o ambiente de desenvolvimento (WSL) **não tem PHP/Docker** — não foi possível executar migração nem testes de runtime localmente. Abaixo, a revisão estática feita e o **checklist de QA a executar na VM** após o deploy autorizado.

**Revisão estática (feita):** rotas/nomes conferidos; binding de modelo por nome de parâmetro; allowlist de campos; guards anti-IDOR; escaping Blade; helper de links; `ConvertEmptyStringsToNull` cobre campos numéricos/data vazios.

**QA funcional a executar na VM (Etapa 28):**
- [ ] Botão Perfil abre o cliente correto; header mostra `#glpi_entity_id` e caminho.
- [ ] Salvar dados gerais/endereço/infra/operacional persiste e reexibe.
- [ ] Adicionar/editar/excluir contato, link e contrato.
- [ ] Ligar/WhatsApp usam o número **daquele** contato; Maps/Waze abrem o endereço certo.
- [ ] Ações não aparecem quando o dado é inválido/inexistente.
- [ ] Desktop e mobile.

**QA de segurança a executar na VM (Etapa 29):**
- [ ] Trocar `{entity}` para uma entidade fora de CLIENTES → 403; ID inexistente → 404.
- [ ] Excluir contato de outra entidade via ID cruzado → 404 (`guardChild`).
- [ ] Cliente final (não-staff) não acessa as rotas → 403.
- [ ] CNPJ inválido é rejeitado; campos de texto não executam script (escapado).
- [ ] Nenhum token aparece no HTML/rede do frontend.

## 7. Riscos / pendências
- QA de runtime na VM ainda **não executado** (limitação do ambiente de dev).
- UX: erro de validação dentro de um modal não reabre o modal (mensagem aparece no topo da página). Não é falha de segurança.
- Fase B (fora deste escopo): resumo técnico ao vivo do GLPI (contagem de ativos/chamados por entidade) e correção do botão **Mapa** (rota órfã).

## 8. Resultado

**APROVADO COM RESSALVAS** — implementação e controles de segurança concluídos e revisados estaticamente; **ressalva única:** executar o checklist de QA (funcional + segurança) na VM após o deploy, por não haver runtime no ambiente de desenvolvimento. Nenhuma vulnerabilidade crítica identificada na revisão (acesso staff-only + guards anti-IDOR + credenciais no backend).

> Push e deploy **não** realizados — aguardando sua autorização, conforme o processo.
