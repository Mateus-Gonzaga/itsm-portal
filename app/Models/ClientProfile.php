<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Ficha do cliente vinculada à ENTIDADE do GLPI por glpi_entity_id.
 * Dados próprios da aplicação (o GLPI segue sendo fonte de identidade/hierarquia).
 */
class ClientProfile extends Model
{
    public const STATUS = ['ativo', 'inativo', 'prospecto', 'suspenso'];

    protected $fillable = [
        'glpi_entity_id', 'razao_social', 'nome_fantasia', 'cnpj', 'segmento', 'status',
        'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'cidade', 'estado',
        'telefone', 'whatsapp', 'email', 'site', 'observacoes',
    ];

    protected function casts(): array
    {
        return ['glpi_entity_id' => 'integer'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function internetLinks(): HasMany
    {
        return $this->hasMany(ClientInternetLink::class);
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(ClientContract::class);
    }

    public function infrastructure(): HasOne
    {
        return $this->hasOne(ClientInfrastructure::class);
    }

    public function operations(): HasOne
    {
        return $this->hasOne(ClientOperation::class);
    }

    /** Endereço em uma linha (para exibição e para montar links de mapa). */
    public function enderecoLinha(): string
    {
        $rua = trim(($this->logradouro ?? '').' '.($this->numero ?? ''));
        $partes = array_filter([$rua, $this->bairro, trim(($this->cidade ?? '').' - '.($this->estado ?? ''), ' -'), $this->cep]);

        return implode(', ', $partes);
    }
}
