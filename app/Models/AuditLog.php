<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Trilha de auditoria de ações sensíveis (inventário, diretório, base de
 * conhecimento). Use AuditLog::record('acao', 'descrição legível').
 */
class AuditLog extends Model
{
    protected $fillable = ['user_id', 'user_name', 'action', 'description', 'ip'];

    /** Registra uma ação sensível do usuário logado (nunca lança erro). */
    public static function record(string $action, string $description): void
    {
        try {
            $user = Auth::user();
            static::create([
                'user_id' => $user?->id,
                'user_name' => $user?->name,
                'action' => $action,
                'description' => mb_substr($description, 0, 1000),
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable) {
            // auditoria nunca deve quebrar a ação principal
        }
    }
}
