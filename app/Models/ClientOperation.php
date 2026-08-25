<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientOperation extends Model
{
    protected $table = 'client_operations';

    protected $fillable = [
        'client_profile_id', 'horario_funcionamento', 'dias_funcionamento', 'atende_fora_horario',
        'restricoes_horario', 'instrucoes_acesso', 'procedimentos_especiais', 'observacoes_operacionais',
        'info_tecnicos', 'observacoes_internas',
    ];

    protected function casts(): array
    {
        return ['atende_fora_horario' => 'boolean'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
