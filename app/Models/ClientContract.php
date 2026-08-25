<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContract extends Model
{
    public const TIPOS = [
        'Suporte técnico', 'Monitoramento', 'Infraestrutura', 'Redes', 'CFTV',
        'Controle de acesso', 'Locação de equipamentos', 'Projeto', 'Outro',
    ];

    public const STATUS = ['ativo', 'suspenso', 'renovacao', 'encerrado'];

    protected $fillable = [
        'client_profile_id', 'numero', 'tipo', 'data_inicio', 'data_termino',
        'status', 'valor_mensal', 'sla', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'data_inicio' => 'date',
            'data_termino' => 'date',
            'valor_mensal' => 'decimal:2',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
