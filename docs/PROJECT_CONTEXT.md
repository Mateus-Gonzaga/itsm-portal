# PROJECT_CONTEXT

Documento principal — informações gerais que mudam raramente.

## Objetivo
Portal web de chamados (ITSM) para abertura, acompanhamento e gestão de chamados de TI, integrado ao GLPI.

## Público-alvo
Clientes (solicitantes), técnicos de TI e gestores da FOURLINE Connect e dos seus clientes (>100 empresas em produção).

## Problema que resolve
Oferece aos clientes uma interface simples, com a marca FOURLINE, para abrir e acompanhar chamados, enquanto o GLPI segue como sistema de origem (service desk). Centraliza e padroniza o atendimento.

## Stack
- PHP 8.5 / **Laravel 12**
- **PostgreSQL 18** (dados do portal: usuários, sessões, cache, jobs)
- Bootstrap 5 + Bootstrap Icons (CDN); fontes Rajdhani/Inter
- Laravel Sail (Docker) sobre WSL2 (Ubuntu 24.04)
- Laravel Fortify (autenticação)
- Integração com GLPI 11.x via API REST (Fase 2)

## Estrutura geral / módulos
- **Auth** — Fortify + perfis (cliente, técnico, gestor)
- **Chamados** — lista, detalhe, abertura, comentários, ações de status
- **Integração GLPI** — camada de repositório trocável (Fake | Api)
- **Dashboard** — métricas por perfil

## Estrutura Docker
- `compose.yaml` (Sail): `laravel.test` (app, porta 80) + `pgsql` (postgres:18, porta 5432).
- GLPI (Fase 2): container próprio + MariaDB/MySQL — **Pendente**.

## Banco de dados
- PostgreSQL 18 para o portal. O GLPI usa MySQL/MariaDB próprio; o portal fala com ele apenas via API.

## Como executar
```bash
wsl -d Ubuntu-24.04 -u mateus
cd ~/itsm-portal
./vendor/bin/sail up -d        # http://localhost  (parar: ./vendor/bin/sail down)
```
Resetar dados da demo (mock): `./vendor/bin/sail artisan cache:clear`

## Variáveis de ambiente principais
- `DB_*` — Postgres do portal
- `GLPI_DRIVER` — `fake` | `api`
- `GLPI_API_URL`, `GLPI_APP_TOKEN`, `GLPI_USER_TOKEN` — Fase 2

## Dependências importantes
- `laravel/framework` ^12, `laravel/fortify`, `laravel/sail`.

## Fluxo geral
Login → painel/chamados conforme o perfil → cliente abre/comenta; técnico/gestor atendem (assumir, mudar status). Os dados vêm do repositório: **mock** na Fase 1, **GLPI** na Fase 2.

## Contas demo (senha `password`)
`cliente@itsm.test` · `tecnico@itsm.test` · `gestor@itsm.test`
