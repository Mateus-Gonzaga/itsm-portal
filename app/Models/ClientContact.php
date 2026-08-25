<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    public const TIPOS = [
        'Responsável pela loja', 'Responsável administrativo', 'Responsável financeiro',
        'Responsável técnico', 'Contato de emergência', 'Outro',
    ];

    protected $fillable = ['client_profile_id', 'nome', 'cargo', 'telefone', 'whatsapp', 'email', 'tipo'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
