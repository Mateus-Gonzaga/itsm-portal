<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Valor (estimado/de compra) de um ativo do inventário — guardado no portal. */
class AssetValue extends Model
{
    protected $fillable = ['itemtype', 'item_id', 'tag', 'modelo', 'value'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'item_id' => 'integer'];
    }
}
