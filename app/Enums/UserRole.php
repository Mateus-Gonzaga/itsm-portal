<?php

namespace App\Enums;

enum UserRole: string
{
    case Cliente = 'cliente';
    case Tecnico = 'tecnico';
    case Gestor = 'gestor';

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
     *
     * @return array<int, array{route: string, label: string, icon: string}>
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
                ['route' => 'dashboard', 'label' => 'Painel', 'icon' => 'bi-house-door'],
                ['route' => 'tickets.index', 'label' => 'Fila de atendimento', 'icon' => 'bi-list-task'],
                ['route' => 'tickets.mine', 'label' => 'Meus chamados', 'icon' => 'bi-ticket-detailed'],
                ['route' => 'agenda.index', 'label' => 'Agenda', 'icon' => 'bi-calendar-week'],
                ['route' => 'modules.inventory', 'label' => 'Inventário', 'icon' => 'bi-pc-display'],
                ['route' => 'tickets.create', 'label' => 'Abrir chamado', 'icon' => 'bi-plus-circle'],
            ],
            self::Gestor => [
                ['route' => 'dashboard', 'label' => 'Painel', 'icon' => 'bi-house-door'],
                ['route' => 'tickets.index', 'label' => 'Todos os chamados', 'icon' => 'bi-card-list'],
                ['route' => 'agenda.index', 'label' => 'Agenda', 'icon' => 'bi-calendar-week'],
                ['route' => 'modules.analytics', 'label' => 'Dashboards', 'icon' => 'bi-grid-1x2'],
                ['route' => 'modules.reports', 'label' => 'Relatórios', 'icon' => 'bi-bar-chart'],
                ['route' => 'modules.inventory', 'label' => 'Inventário', 'icon' => 'bi-pc-display'],
                ['route' => 'modules.clients', 'label' => 'Clientes', 'icon' => 'bi-people'],
                ['route' => 'modules.technicians', 'label' => 'Técnicos', 'icon' => 'bi-person-badge'],
                ['route' => 'modules.schedule', 'label' => 'Janela de atendimento', 'icon' => 'bi-clock-history'],
                ['route' => 'modules.settings', 'label' => 'Configurações', 'icon' => 'bi-gear'],
            ],
        };
    }
}
