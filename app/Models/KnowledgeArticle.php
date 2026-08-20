<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Artigo da Base de Conhecimento (info por cliente/filial: contrato, ativos, etc.). */
class KnowledgeArticle extends Model
{
    public function attachments(): HasMany
    {
        return $this->hasMany(KbAttachment::class, 'kb_article_id');
    }

    protected $table = 'kb_articles';

    public const CATEGORIAS = ['Contrato', 'Internet', 'Documentos / Modelos', 'Ativos / Valores', 'Infraestrutura', 'Contatos', 'Acessos', 'Geral'];

    protected $fillable = [
        'cliente',
        'categoria',
        'titulo',
        'valor',
        'conteudo',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['valor' => 'decimal:2'];
    }
}
