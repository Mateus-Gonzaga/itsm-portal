<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Regra única de "qual é o cliente" de um chamado (Painel, Relatórios…):
 * a empresa = primeiro nível depois de CLIENTES ("Drogacei › FL 08" → "Drogacei").
 * Chamados presos na raiz/pasta CLIENTES viram "Sem cliente definido".
 */
final class ClientName
{
    public const SEM_CLIENTE = 'Sem cliente definido';

    public static function company(?string $entity): string
    {
        $name = trim(Str::before(html_entity_decode((string) $entity, ENT_QUOTES | ENT_HTML5, 'UTF-8'), '›'));

        return in_array(mb_strtolower($name), ['', '—', 'entidade raiz', 'root entity', 'clientes'], true)
            ? self::SEM_CLIENTE
            : $name;
    }

    /** Filial/unidade ("Drogacei › FL 08" → "FL 08"); vazio se não houver. */
    public static function branch(?string $entity): string
    {
        $entity = html_entity_decode((string) $entity, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return str_contains($entity, '›') ? trim(Str::after($entity, '›')) : '';
    }
}
