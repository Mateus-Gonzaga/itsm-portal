<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInternetLink extends Model
{
    public const PAPEIS = ['principal', 'contingencia'];

    public const TIPOS = ['Fibra', 'Cabo', 'Rádio', 'Satélite', 'Starlink', 'Outro'];

    protected $fillable = [
        'client_profile_id', 'papel', 'provedora', 'plano',
        'velocidade_download', 'velocidade_upload', 'tipo_conexao', 'ip_publico', 'observacoes',
    ];

    protected function casts(): array
    {
        return ['ip_publico' => 'boolean'];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
