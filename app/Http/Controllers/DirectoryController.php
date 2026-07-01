<?php

namespace App\Http\Controllers;

use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Escrita do diretório GLPI (entidades e usuários) a partir das telas
 * Clientes/Técnicos. Restrito ao gestor (rotas com role:gestor).
 */
class DirectoryController extends Controller
{
    public function __construct(
        private readonly GlpiDirectoryRepositoryInterface $dir,
    ) {
    }

    /**
     * Defesa: garante que perfil/entidade estão na lista permitida, mesmo que
     * alguém adultere o formulário. Bloqueia criar Super-Admin ou usar entidades
     * fora da árvore de Clientes.
     */
    private function guardAssignment(int $profileId, int $entityId): void
    {
        $okProfile = $this->dir->profiles()
            ->whereIn('name', (array) config('portal.assignable_profiles', []))
            ->contains(fn ($p) => (int) $p['id'] === $profileId);

        $okEntity = $this->dir->entities()
            ->filter(fn ($e) => str_contains($e['completename'], 'CLIENTES'))
            ->contains(fn ($e) => (int) $e['id'] === $entityId);

        abort_unless($okProfile, 403, 'Perfil não permitido para atribuição.');
        abort_unless($okEntity, 403, 'Entidade fora do escopo de Clientes.');
    }

    public function storeEntity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['required', 'integer'],
        ]);

        $this->dir->createEntity($data['name'], (int) $data['parent_id']);

        return back()->with('status', "Entidade \"{$data['name']}\" criada.");
    }

    public function updateEntity(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);

        $this->dir->updateEntity($id, $data['name']);

        return back()->with('status', 'Entidade atualizada.');
    }

    public function storeUser(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:150'],
            'password' => ['required', 'string', 'min:6'],
            'entity_id' => ['required', 'integer'],
            'profile_id' => ['required', 'integer'],
        ]);

        $this->guardAssignment((int) $data['profile_id'], (int) $data['entity_id']);

        $this->dir->createUser([
            'login' => $data['login'],
            'name' => $data['name'],
            'password' => $data['password'],
            'entity_id' => (int) $data['entity_id'],
            'profile_id' => (int) $data['profile_id'],
            'recursive' => $request->boolean('recursive'),
            'active' => $request->boolean('active'),
        ]);

        return back()->with('status', "Usuário \"{$data['login']}\" criado.");
    }

    public function updateUser(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'entity_id' => ['required', 'integer'],
            'profile_id' => ['required', 'integer'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        $this->guardAssignment((int) $data['profile_id'], (int) $data['entity_id']);

        $this->dir->updateUser($id, [
            'name' => $data['name'],
            'entity_id' => (int) $data['entity_id'],
            'profile_id' => (int) $data['profile_id'],
            'recursive' => $request->boolean('recursive'),
            'active' => $request->boolean('active'),
            'password' => $data['password'] ?? null,
        ]);

        return back()->with('status', 'Usuário atualizado.');
    }

    public function toggleUser(Request $request, int $id): RedirectResponse
    {
        $active = $request->boolean('active');
        $this->dir->setUserActive($id, $active);

        return back()->with('status', $active ? 'Usuário ativado.' : 'Usuário desativado.');
    }

    /**
     * Isola um cliente em 1 passo: cria (ou reaproveita) a entidade da loja
     * sob CLIENTES e move o usuário para ela (escopo "somente esta"). Resolve o
     * problema de vários clientes ficarem soltos na entidade-pai CLIENTES.
     */
    public function isolateUser(Request $request, int $id): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'entity_name' => ['required', 'string', 'max:255'],
            'profile_id' => ['required', 'integer'],
        ]);

        // Perfil precisa ser atribuível (evita virar Super-Admin via form).
        $okProfile = $this->dir->profiles()
            ->whereIn('name', (array) config('portal.assignable_profiles', []))
            ->contains(fn ($p) => (int) $p['id'] === (int) $data['profile_id']);
        abort_unless($okProfile, 403, 'Perfil não permitido para atribuição.');

        // Entidade-pai = a entidade "CLIENTES".
        $entities = $this->dir->entities();
        $clientes = $entities->first(fn ($e) => $e['name'] === 'CLIENTES');
        abort_unless($clientes, 422, 'Entidade "CLIENTES" não encontrada no GLPI.');
        $parentId = (int) $clientes['id'];

        // Reaproveita entidade de mesmo nome já existente sob CLIENTES; senão cria.
        $target = $entities->first(fn ($e) => $e['name'] === $data['entity_name']
            && str_contains(html_entity_decode((string) $e['completename']), 'CLIENTES'));
        $entityId = $target ? (int) $target['id'] : $this->dir->createEntity($data['entity_name'], $parentId);

        $this->dir->updateUser($id, [
            'name' => $data['name'],
            'entity_id' => $entityId,
            'profile_id' => (int) $data['profile_id'],
            'recursive' => false,
            'active' => true,
            'password' => null,
        ]);

        return back()->with('status', "Cliente isolado na entidade \"{$data['entity_name']}\".");
    }
}
