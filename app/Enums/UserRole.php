<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum UserRole: string
{
    case Cliente = 'cliente';
    case Tecnico = 'tecnico';
    case Gestor = 'gestor';

    /**
     * Converte o nome do perfil do GLPI no papel do portal.
     * Fonte de verdade: config/portal.php (profile_roles). Perfis não listados
     * caem no fallback por palavra-chave (ex.: "Super-Admin" → gestor). Usado no
     * login E nas listas de equipe, para ficarem sempre consistentes.
     */
    public static function fromGlpiProfile(string $profile): self
    {
        $map = (array) config('portal.profile_roles', []);
        if (isset($map[$profile]) && ($r = self::tryFrom($map[$profile]))) {
            return $r;
        }

        $p = Str::lower($profile);

        return match (true) {
            str_contains($p, 'gestor'), str_contains($p, 'admin') => self::Gestor, // 'admin' cobre 'Super-Admin'
            str_contains($p, 'técnico'), str_contains($p, 'tecnico'), str_contains($p, 'technician'), str_contains($p, 'supervisor') => self::Tecnico,
            default => self::Cliente,
        };
    }

    /** Papéis considerados "equipe" (podem ser responsáveis/atribuídos). */
    public function isStaff(): bool
    {
        return $this === self::Tecnico || $this === self::Gestor;
    }

    public function label(): string
    {
        return match ($this) {
            self::Cliente => 'Cliente',
            self::Tecnico => 'Técnico',
            self::Gestor => 'Gestor',
        };
    }

    /**
     * Itens da sidebar por perfil (fonte do menu dinâmico).
     * Cliente = enxuto; Técnico = fila + meus chamados; Gestor = completo.
     * Itens sem página real apontam para o placeholder de módulo.
     * 'group' (opcional) agrupa os itens em seções com título no menu lateral;
     * o menu do cliente é curto e fica sem seções.
     *
     * @return array<int, array{route: string, label: string, icon: string, group?: string}>
     */
    public function menu(): array
    {
        return match ($this) {
            self::Cliente => [
                ['route' => 'dashboard', 'label' => 'Painel', 'icon' => 'bi-house-door'],
                ['route' => 'tickets.index', 'label' => 'Meus chamados', 'icon' => 'bi-ticket-detailed'],
                ['route' => 'modules.inventory', 'label' => 'Inventário', 'icon' => 'bi-pc-display'],
                ['route' => 'tickets.create', 'label' => 'Abrir chamado', 'icon' => 'bi-plus-circle'],
            ],
            self::Tecnico => [
                ['group' => 'Atendimento', 'route' => 'dashboard', 'label' => 'Painel', 'icon' => 'bi-house-door'],
                ['group' => 'Atendimento', 'route' => 'tickets.index', 'label' => 'Fila de atendimento', 'icon' => 'bi-list-task'],
                ['group' => 'Atendimento', 'route' => 'tickets.mine', 'label' => 'Meus chamados', 'icon' => 'bi-ticket-detailed'],
                ['group' => 'Atendimento', 'route' => 'agenda.index', 'label' => 'Agenda', 'icon' => 'bi-calendar-week'],
                ['group' => 'Atendimento', 'route' => 'tickets.create', 'label' => 'Abrir chamado', 'icon' => 'bi-plus-circle'],
                ['group' => 'Gestão', 'route' => 'modules.inventory', 'label' => 'Inventário', 'icon' => 'bi-pc-display'],
            ],
            self::Gestor => [
                ['group' => 'Atendimento', 'route' => 'dashboard', 'label' => 'Painel', 'icon' => 'bi-house-door'],
                ['group' => 'Atendimento', 'route' => 'tickets.index', 'label' => 'Todos os chamados', 'icon' => 'bi-card-list'],
                ['group' => 'Atendimento', 'route' => 'agenda.index', 'label' => 'Agenda', 'icon' => 'bi-calendar-week'],
                ['group' => 'Gestão', 'route' => 'modules.analytics', 'label' => 'Dashboards', 'icon' => 'bi-grid-1x2'],
                ['group' => 'Gestão', 'route' => 'modules.reports', 'label' => 'Relatórios', 'icon' => 'bi-bar-chart'],
                ['group' => 'Gestão', 'route' => 'modules.inventory', 'label' => 'Inventário', 'icon' => 'bi-pc-display'],
                ['group' => 'Cadastros', 'route' => 'modules.clients', 'label' => 'Clientes', 'icon' => 'bi-people'],
                ['group' => 'Cadastros', 'route' => 'modules.kb', 'label' => 'Base de conhecimento', 'icon' => 'bi-journal-text'],
                ['group' => 'Cadastros', 'route' => 'modules.technicians', 'label' => 'Técnicos', 'icon' => 'bi-person-badge'],
                ['group' => 'Sistema', 'route' => 'modules.audit', 'label' => 'Auditoria', 'icon' => 'bi-shield-lock'],
                ['group' => 'Sistema', 'route' => 'modules.settings', 'label' => 'Configurações', 'icon' => 'bi-gear'],
            ],
        };
    }
}
