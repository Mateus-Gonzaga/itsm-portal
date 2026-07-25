<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Artigo da Base de Conhecimento (info por cliente/filial: contrato, ativos, etc.). */
class KnowledgeArticle extends Model
{
    protected $table = 'kb_articles';

    public const CATEGORIAS = ['Contrato', 'Ativos / Valores', 'Infraestrutura', 'Contatos', 'Acessos', 'Geral'];

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
