# ARCHITECTURE

Apenas arquitetura (não implementação). Atualizar só em mudança arquitetural.

## Backend
- Laravel 12 (MVC), controllers finos em `app/Http/Controllers`.
- **Camada de repositório** isola o acesso a chamados: controllers dependem só de `GlpiTicketRepositoryInterface`.
- DTOs neutros (`app/Data/TicketData`, `TicketComment`) — o app não conhece o formato do GLPI.
- Enums com comportamento em `app/Enums` (UserRole, TicketStatus, TicketPriority, TicketType).

## Frontend
- Blade + Bootstrap 5 (CDN). **Layout SaaS** (`layouts/app.blade.php`): sidebar fixa + topbar; **menu dinâmico** por perfil (`UserRole::menu()`).
- **Design System** centralizado em `public/css/brand.css` (tokens de cor/espaçamento/raio/sombra). **Tema claro/escuro** via `data-bs-theme` (persistido em localStorage). Ver `DESIGN_SYSTEM.md`.
- Módulos futuros já com rota + item de menu como placeholders (Inventário, Base de conhecimento, Dashboard, Monitoramento, Automações).

## Estrutura das pastas (resumo)
- `app/Http/Controllers`, `app/Http/Middleware`, `app/Enums`, `app/Data`, `app/Repositories/Glpi`, `app/Providers`
- `resources/views/{layouts,auth,dashboard,tickets}`
- `routes/web.php`, `config/glpi.php`, `public/css`, `public/*.png`

## Fluxo das requisições
HTTP → `routes/web.php` → middleware (`auth`, `role`) → Controller → `GlpiTicketRepositoryInterface` → (Fake | Api) → DTO → Blade.

## Padrões
- Repository + Strategy (driver trocável por flag).
- DTOs / Value Objects imutáveis.
- Enums com comportamento (`label`/`color`/`icon`/`menu`).

## Regras arquiteturais
- Nada acima do repositório acessa o GLPI diretamente.
- Mudou o contrato (DTO/interface) → atualizar a interface e todas as implementações juntas.
- Catálogos (categorias etc.) virão do GLPI na Fase 2.

## Autenticação
- Laravel Fortify (login/logout, guard `web`), com view Bootstrap própria.

## Autorização
- Perfil em `User::role` (enum). Middleware `role` (`EnsureUserHasRole`) por rota.
- Cliente só acessa os próprios chamados (checagem no controller). Fase 2: isolamento por **Entity** do GLPI (multi-cliente).

## Decisões técnicas (e motivos)
- **Repositório trocável (Fake→Api por flag):** construir/demonstrar sem depender do GLPI e migrar para produção sem reescrever.
- **DTO neutro:** desacoplar do JSON do GLPI; trocar o driver não quebra as views.
- **Fortify (não Breeze/Jetstream):** evitar Tailwind, manter 100% Bootstrap.
- **Sail/WSL2:** ambiente local espelhando produção (Linux + Postgres).
- **Postgres no portal, MySQL no GLPI:** separação de responsabilidades; comunicação só via API.
