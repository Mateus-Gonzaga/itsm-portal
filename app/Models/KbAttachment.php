<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Anexo de um artigo da Base de Conhecimento (contrato em PDF, imagem, etc.). */
class KbAttachment extends Model
{
    protected $fillable = ['kb_article_id', 'original_name', 'path', 'mime'];

    protected static function booted(): void
    {
        // Ao excluir o registro, apaga o arquivo físico.
        static::deleting(function (KbAttachment $a) {
            Storage::disk('local')->delete($a->path);
        });
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'kb_article_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
