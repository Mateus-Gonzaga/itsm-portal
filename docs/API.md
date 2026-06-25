# API

## APIs internas (rotas web do portal)
Autenticação por sessão (Fortify). POSTs exigem CSRF. Respostas em HTML ou redirect 302.

| Método | URL | Objetivo | Perfil |
|---|---|---|---|
| GET | `/login` | Tela de login | pública |
| POST | `/login` | Autentica (Fortify) | pública |
| POST | `/logout` | Encerra sessão | todos |
| GET | `/dashboard` | Painel + métricas | todos |
| GET | `/tickets` | Lista chamados (filtro `?status=`) | todos |
| GET | `/tickets/create` | Formulário de abertura | cliente |
| POST | `/tickets` | Abre chamado | cliente |
| GET | `/tickets/{id}` | Detalhe + timeline | todos\* |
| POST | `/tickets/{id}/comments` | Adiciona comentário | todos\* |
| POST | `/tickets/{id}/assign` | Assume o chamado | técnico, gestor |
| POST | `/tickets/{id}/status` | Muda status | técnico, gestor |
| POST | `/tickets/{id}/approve` | Aprova solução (fecha) | cliente (dono) |
| POST | `/tickets/{id}/reopen` | Reabre | cliente (dono) |

\* cliente só acessa os próprios chamados (senão 403).

### Bodies
- **POST `/tickets`:** `title` (req), `description` (req), `type` (req: `incident`|`request`), `category` (opc), `priority` (req: `low`|`medium`|`high`|`urgent`).
- **POST `/tickets/{id}/comments`:** `content` (req).
- **POST `/tickets/{id}/status`:** `status` (req, enum TicketStatus), `note` (opc).

### Códigos HTTP
200 (ok) · 302 (redirect pós-ação) · 403 (sem permissão) · 404 (não encontrado) · 422 (validação).

## API externa (GLPI 11.x) — Pendente (Fase 2)
Documentar os endpoints usados (sessão/auth, `Ticket`, `ITILFollowup`, etc.) quando o `ApiGlpiTicketRepository` for implementado.
