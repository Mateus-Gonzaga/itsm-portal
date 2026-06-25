<?php

namespace App\Repositories\Glpi;

use Illuminate\Support\Collection;

/** Diretório de demonstração (modo fake), sem tocar no GLPI. */
class FakeGlpiDirectoryRepository implements GlpiDirectoryRepositoryInterface
{
    public function entities(): Collection
    {
        return collect([
            ['id' => 1, 'name' => 'CLIENTES', 'completename' => 'Entidade raiz > CLIENTES', 'level' => 2],
            ['id' => 2, 'name' => 'Drogacei', 'completename' => 'Entidade raiz > CLIENTES > Drogacei', 'level' => 3],
            ['id' => 3, 'name' => 'FL 01 - Setor O', 'completename' => 'Entidade raiz > CLIENTES > Drogacei > FL 01 - Setor O', 'level' => 4],
            ['id' => 4, 'name' => 'Mel do Sol', 'completename' => 'Entidade raiz > CLIENTES > Mel do Sol', 'level' => 3],
        ]);
    }

    public function profiles(): Collection
    {
        return collect([
            ['id' => 1, 'name' => 'Self-Service', 'interface' => 'Autoatendimento'],
            ['id' => 9, 'name' => 'Técnico FL', 'interface' => 'Completa'],
            ['id' => 10, 'name' => 'Gestor - Clientes', 'interface' => 'Completa'],
        ]);
    }

    public function users(): Collection
    {
        return collect([
            ['id' => 21, 'login' => 'drogacei01', 'name' => 'drogacei01', 'active' => true, 'profile_id' => 1, 'profile' => 'Self-Service', 'entity_id' => 3, 'entity' => 'Entidade raiz > CLIENTES > Drogacei > FL 01 - Setor O', 'recursive' => false],
            ['id' => 18, 'login' => 'meldosol', 'name' => 'meldosol', 'active' => true, 'profile_id' => 1, 'profile' => 'Self-Service', 'entity_id' => 4, 'entity' => 'Entidade raiz > CLIENTES > Mel do Sol', 'recursive' => false],
            ['id' => 45, 'login' => 'joao.fourline', 'name' => 'joao.fourline', 'active' => true, 'profile_id' => 9, 'profile' => 'Técnico FL', 'entity_id' => 1, 'entity' => 'Entidade raiz > CLIENTES', 'recursive' => true],
            ['id' => 44, 'login' => 'mateus.fourline', 'name' => 'mateus.fourline', 'active' => true, 'profile_id' => 10, 'profile' => 'Gestor - Clientes', 'entity_id' => 1, 'entity' => 'Entidade raiz > CLIENTES', 'recursive' => true],
        ]);
    }

    public function createEntity(string $name, int $parentId): void
    {
        // no-op (demo)
    }

    public function updateEntity(int $id, string $name): void
    {
        // no-op (demo)
    }

    public function createUser(array $data): void
    {
        // no-op (demo)
    }

    public function updateUser(int $id, array $data): void
    {
        // no-op (demo)
    }

    public function setUserActive(int $id, bool $active): void
    {
        // no-op (demo)
    }
}
