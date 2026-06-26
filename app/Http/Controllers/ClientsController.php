<?php

namespace App\Http\Controllers;

use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientsController extends Controller
{
    public function __invoke(Request $request, GlpiDirectoryRepositoryInterface $dir): View
    {
        $entities = $dir->entities();
        $users = $dir->users();
        $profiles = $dir->profiles();

        // "Clientes" = entidades de negócio (exclui a raiz e o nó CLIENTES).
        $clientes = $entities->reject(fn ($e) => $e['level'] <= 2);

        // Só estes perfis podem ser atribuídos pela tela (espelha a trava do
        // DirectoryController::guardAssignment — evita oferecer Super-Admin etc.).
        $assignableProfiles = $profiles
            ->whereIn('name', (array) config('portal.assignable_profiles', []))
            ->values();

        return view('modules.clients', [
            'entities' => $entities,
            'users' => $users,
            'profiles' => $profiles,
            'assignableProfiles' => $assignableProfiles,
            'stats' => [
                'clientes' => $clientes->count(),
                'usuarios' => $users->count(),
                'perfis' => $profiles->count(),
            ],
        ]);
    }
}
