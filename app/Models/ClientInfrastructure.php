<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientInfrastructure extends Model
{
    protected $table = 'client_infrastructure';

    protected $fillable = [
        'client_profile_id', 'possui_firewall', 'firewall_modelo', 'possui_roteador', 'roteador_modelo',
        'switch_principal', 'quantidade_switches', 'wifi_corporativo', 'quantidade_access_points', 'observacoes',
    ];

    protected function casts(): array
    {
        return [
            'possui_firewall' => 'boolean',
            'possui_roteador' => 'boolean',
            'wifi_corporativo' => 'boolean',
            'quantidade_switches' => 'integer',
            'quantidade_access_points' => 'integer',
        ];
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(ClientProfile::class);
    }
}
