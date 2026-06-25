<?php

namespace App\Repositories\Glpi;

use Illuminate\Support\Collection;

/**
 * Inventário do GLPI (ativos). Leitura escopada pela entidade do usuário
 * logado (o token do usuário aplica o isolamento nativamente).
 *
 * Mesmo padrão trocável: Fake (demo) / Api (GLPI real) via GLPI_DRIVER.
 */
interface GlpiInventoryRepositoryInterface
{
    /**
     * Tipos de ativo suportados.
     *
     * @return array<string, array{label:string,icon:string}>
     */
    public function types(): array;

    /**
     * Ativos (todos os tipos), normalizados.
     *
     * @return Collection<int, array{id:int,type:string,typeKey:string,icon:string,name:string,entity:string,status:string,serial:string,model:string,manufacturer:string,location:string}>
     */
    public function assets(): Collection;
}
