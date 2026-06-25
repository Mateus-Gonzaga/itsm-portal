<?php

namespace App\Repositories\Glpi;

use Illuminate\Support\Collection;

/**
 * Diretório do GLPI: entidades (clientes/filiais), perfis e usuários.
 * Leitura + escrita (criar/editar) para as telas Clientes e Técnicos (gestor).
 *
 * Mesmo padrão trocável: Fake (demo) / Api (GLPI real) via GLPI_DRIVER.
 */
interface GlpiDirectoryRepositoryInterface
{
    /** @return Collection<int, array{id:int,name:string,completename:string,level:int}> */
    public function entities(): Collection;

    /** @return Collection<int, array{id:int,name:string,interface:string}> */
    public function profiles(): Collection;

    /**
     * Um registro por usuário (não-sistema) com o acesso principal resolvido.
     *
     * @return Collection<int, array{id:int,login:string,name:string,active:bool,profile_id:int,profile:string,entity_id:int,entity:string,recursive:bool}>
     */
    public function users(): Collection;

    public function createEntity(string $name, int $parentId): void;

    public function updateEntity(int $id, string $name): void;

    /** @param array{login:string,name:string,password:string,entity_id:int,profile_id:int,recursive:bool,active:bool} $data */
    public function createUser(array $data): void;

    /** @param array{name:string,active:bool,entity_id:int,profile_id:int,recursive:bool,password?:?string} $data */
    public function updateUser(int $id, array $data): void;

    /** Ativa/desativa um usuário (ação rápida). */
    public function setUserActive(int $id, bool $active): void;
}
