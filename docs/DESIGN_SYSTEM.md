# DESIGN SYSTEM — FOURLINE

Design System centralizado em `public/css/brand.css` (tokens via CSS variables). Base: Bootstrap 5.3 + Bootstrap Icons. Tema claro/escuro via atributo `data-bs-theme` no `<html>` (persistido em `localStorage`, alternado pelo botão na topbar).

## Princípios
- Layout SaaS com **sidebar fixa** (verde da marca) + conteúdo claro.
- Consistência entre os perfis Cliente, Técnico e Gestor (mesmo shell e componentes).
- Cliente = enxuto/funcional; Gestor = completo.

## Temas
- **Claro (padrão):** fundo `#F8FAFC`, superfícies brancas.
- **Escuro:** fundo `#0b1220`, superfícies escuras.
- Alternância: botão na topbar → `toggleTheme()` (seta `data-bs-theme` e salva em `localStorage`). Componentes do Bootstrap acompanham automaticamente.

## Cores (tokens)
Marca: `--fl-green #068A4F` · `--fl-green-light #A8CF45` · `--fl-green-dark #045c34` · `--fl-green-xdark #033d22`.
Semânticas (mudam por tema): `--app-bg`, `--surface`, `--surface-2`, `--border-c`, `--text-c`, `--muted-c`, `--sidebar-bg`, `--sidebar-text`, `--sidebar-active`.

## Espaçamento
Escala fixa (use sempre estes): **4, 8, 12, 16, 24, 32, 48, 64 px** → `--sp-1` … `--sp-16` (`--sp-1`=4, `--sp-2`=8, `--sp-3`=12, `--sp-4`=16, `--sp-6`=24, `--sp-8`=32, `--sp-12`=48, `--sp-16`=64).

## Raio
`--radius-sm` 8px · `--radius` **16px** (cards) · `--radius-pill` 999px (chips/badges).

## Sombra
`--shadow-sm` · `--shadow` (cards) · `--shadow-lg`.

## Tipografia
Títulos: **Rajdhani**. Texto: **Inter**. (Google Fonts via CDN.)

## Layout
- `--sidebar-w` 256px · `--topbar-h` 60px.
- `.app-shell` → `.sidebar` (fixa) + `.content` (`.topbar` sticky + `.page`).
- Responsivo: < 992px a sidebar vira off-canvas (botão hambúrguer na topbar).

## Componentes
- **Card:** fundo `--surface`, borda `--border-c`, **raio 16px**, `--shadow`. (`.card`)
- **Botão primário:** verde da marca (`.btn-primary`); CTA principal de cada tela usa `.btn-primary.btn-cta` (com sombra destacada).
- **Tabela:** envolver em `.table-wrap` (scroll + cabeçalho fixo via `position: sticky`); `.table-hover` para hover nas linhas. Busca + filtros rápidos (`.filter-chip`) + paginação (`->links()`, Bootstrap 5 via `Paginator::useBootstrapFive()`).
- **Filter chip:** `.filter-chip` (pílula); ativo = verde.
- **Badges de status/prioridade:** `bg-{cor}` / `bg-{cor}-subtle text-{cor}-emphasis` (cores vêm de `TicketStatus`/`TicketPriority`).

## Sidebar (menu por perfil)
Fonte: `App\Enums\UserRole::menu()`. Itens: `{route, label, icon}`. Módulos futuros já presentes como placeholders: Inventário, Base de conhecimento, Dashboard, Monitoramento, Automações.

## Como usar / estender
- Novas telas: estender `layouts.app`, usar `.card`, escala de espaçamento e `.btn-primary.btn-cta` no botão principal.
- Nunca usar cores/medidas "soltas" — sempre os tokens.
- Atualizar este arquivo ao mudar tokens ou adicionar componentes.
