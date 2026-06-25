<?php

namespace App\Enums;

/**
 * Prioridade NEUTRA de um chamado. Na Fase 2, o ApiGlpiTicketRepository
 * mapeia as prioridades do GLPI (1 a 6) para estes casos.
 */
enum TicketPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Baixa',
            self::Medium => 'Média',
            self::High => 'Alta',
            self::Urgent => 'Urgente',
        };
    }

    /** Cor do badge Bootstrap para esta prioridade. */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'success',
            self::Medium => 'secondary',
            self::High => 'warning',
            self::Urgent => 'danger',
        };
    }
}
