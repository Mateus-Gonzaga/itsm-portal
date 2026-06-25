<?php

namespace App\Enums;

/**
 * Status NEUTRO de um chamado. Na Fase 2, o ApiGlpiTicketRepository traduz
 * os códigos numéricos do GLPI (1=novo, 2=atribuído, ...) para estes casos.
 */
enum TicketStatus: string
{
    case New = 'new';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Pending = 'pending';
    case Solved = 'solved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Novo',
            self::Assigned => 'Atribuído',
            self::InProgress => 'Em andamento',
            self::Pending => 'Pendente',
            self::Solved => 'Resolvido',
            self::Closed => 'Fechado',
        };
    }

    /** Cor do badge Bootstrap para este status. */
    public function color(): string
    {
        return match ($this) {
            self::New => 'secondary',
            self::Assigned => 'info',
            self::InProgress => 'primary',
            self::Pending => 'warning',
            self::Solved => 'success',
            self::Closed => 'dark',
        };
    }
}
