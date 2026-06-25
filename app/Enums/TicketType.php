<?php

namespace App\Enums;

/**
 * Tipo NEUTRO do chamado. No GLPI: type 1 = incidente, 2 = requisição.
 */
enum TicketType: string
{
    case Incident = 'incident';
    case Request = 'request';

    public function label(): string
    {
        return match ($this) {
            self::Incident => 'Incidente',
            self::Request => 'Requisição',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Incident => 'bi-exclamation-triangle',
            self::Request => 'bi-card-checklist',
        };
    }
}
