<?php

namespace App\Repositories\Glpi;

use App\Repositories\Glpi\Support\GlpiCache;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

/**
 * Diretório lido/gravado na API REST do GLPI (entidades, perfis, usuários).
 * Autocontido (própria sessão), como os demais ApiGlpi*Repository.
 */
class ApiGlpiDirectoryRepository implements GlpiDirectoryRepositoryInterface
{
    /** Contas internas/sistema do GLPI — fora das telas de cliente. */
    private const SYSTEM = ['glpi', 'glpi-system', 'post-only', 'tech', 'normal', 'zabbix'];

    private ?string $sessionToken = null;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $appToken,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    /** Diretório muda pouco: cache de 5 min (conta de serviço), invalidado nas escritas. */
    private const TTL = 300;

    public function entities(): Collection
    {
        return GlpiCache::remember('directory', 'entities', self::TTL, fn () => $this->loadEntities());
    }

    private function loadEntities(): Collection
    {
        return collect(GlpiCache::fetchAll($this->client(), '/Entity', ['expand_dropdowns' => 'true']))
            ->map(fn (array $e) => [
                'id' => (int) ($e['id'] ?? 0),
                'name' => (string) ($e['name'] ?? ''),
                'completename' => (string) ($e['completename'] ?? ($e['name'] ?? '')),
                'level' => (int) ($e['level'] ?? 1),
            ])
            ->sortBy('completename')
            ->values();
    }

    public function profiles(): Collection
    {
        return GlpiCache::remember('directory', 'profiles', self::TTL, fn () => $this->loadProfiles());
    }

    private function loadProfiles(): Collection
    {
        return collect(GlpiCache::fetchAll($this->client(), '/Profile'))
            ->map(fn (array $p) => [
                'id' => (int) ($p['id'] ?? 0),
                'name' => (string) ($p['name'] ?? ''),
                'interface' => ($p['interface'] ?? '') === 'helpdesk' ? 'Autoatendimento' : 'Completa',
            ])
            ->sortBy('name')
            ->values();
    }

    public function users(): Collection
    {
        return GlpiCache::remember('directory', 'users', self::TTL, fn () => $this->loadUsers());
    }

    private function loadUsers(): Collection
    {
        $users = GlpiCache::fetchAll($this->client(), '/User');
        $profileUsers = GlpiCache::fetchAll($this->client(), '/Profile_User');
        if ($users === []) {
            return collect();
        }

        $entityNames = $this->entities()->keyBy('id')->map(fn ($e) => $e['completename']);
        $profileNames = $this->profiles()->keyBy('id')->map(fn ($p) => $p['name']);

        // Agrupa Profile_User por usuário e escolhe o vínculo PRINCIPAL:
        // não-dinâmico e fora da raiz; senão não-dinâmico; senão o primeiro.
        $byUser = collect($profileUsers)->groupBy('users_id');
        $primary = function (int $uid) use ($byUser) {
            $rows = $byUser->get($uid, collect());
            if ($rows instanceof Collection === false) {
                $rows = collect($rows);
            }

            return $rows->sortByDesc(fn ($r) => (! (bool) ($r['is_dynamic'] ?? false) ? 2 : 0) + ((int) ($r['entities_id'] ?? 0) !== 0 ? 1 : 0))->first();
        };

        return collect($users)
            ->reject(fn (array $u) => in_array((string) ($u['name'] ?? ''), self::SYSTEM, true))
            ->map(function (array $u) use ($primary, $entityNames, $profileNames) {
                $login = (string) ($u['name'] ?? '');
                $sobrenome = trim((string) ($u['realname'] ?? ''));
                $nome = trim((string) ($u['firstname'] ?? ''));
                // Dedup: contas de loja costumam ter o MESMO texto nos dois campos
                // ("ADAL"+"ADAL") — não repetir na exibição.
                $real = mb_strtolower($sobrenome) === mb_strtolower($nome)
                    ? $sobrenome
                    : trim($sobrenome.' '.$nome);
                $pu = $primary((int) ($u['id'] ?? 0));

                return [
                    'id' => (int) ($u['id'] ?? 0),
                    'login' => $login,
                    'name' => $real !== '' ? $real : $login,
                    'active' => (bool) ($u['is_active'] ?? true),
                    'profile_id' => (int) ($pu['profiles_id'] ?? 0),
                    'profile' => $profileNames[(int) ($pu['profiles_id'] ?? 0)] ?? '—',
                    'entity_id' => (int) ($pu['entities_id'] ?? 0),
                    'entity' => $entityNames[(int) ($pu['entities_id'] ?? 0)] ?? '—',
                    'recursive' => (bool) ($pu['is_recursive'] ?? false),
                ];
            })
            ->sortBy('name')
            ->values();
    }

    // ----------------------------------------------------------------
    // Escrita
    // ----------------------------------------------------------------

    public function createEntity(string $name, int $parentId): int
    {
        $resp = $this->client()->post('/Entity', ['input' => ['name' => $name, 'entities_id' => $parentId]]);
        $resp->throw();
        GlpiCache::flush('directory');

        return (int) ($resp->json('id') ?? $resp->json('0.id'));
    }

    public function updateEntity(int $id, string $name): void
    {
        $this->client()->put("/Entity/{$id}", ['input' => ['id' => $id, 'name' => $name]])->throw();
        GlpiCache::flush('directory');
    }

    public function createUser(array $data): void
    {
        $resp = $this->client()->post('/User', ['input' => [
            'name' => $data['login'],
            'realname' => $data['name'],
            'password' => $data['password'],
            'password2' => $data['password'],
            'is_active' => $data['active'] ? 1 : 0,
            'entities_id' => $data['entity_id'],
        ]]);
        $resp->throw();
        $uid = (int) ($resp->json('id') ?? $resp->json('0.id'));

        $this->client()->post('/Profile_User', ['input' => [
            'users_id' => $uid,
            'profiles_id' => $data['profile_id'],
            'entities_id' => $data['entity_id'],
            'is_recursive' => $data['recursive'] ? 1 : 0,
            'is_dynamic' => 0,
        ]])->throw();
        GlpiCache::flush('directory');
    }

    public function updateUser(int $id, array $data): void
    {
        // O "Nome de exibição" do portal é a fonte única: grava no realname e
        // LIMPA o firstname — senão o firstname antigo continua concatenado na
        // exibição (causa do "ADAL ADAL" e da edição que "não pegava").
        $input = ['id' => $id, 'realname' => $data['name'], 'firstname' => '', 'is_active' => $data['active'] ? 1 : 0];
        if (! empty($data['password'])) {
            $input['password'] = $data['password'];
            $input['password2'] = $data['password'];
        }
        $this->client()->put("/User/{$id}", ['input' => $input])->throw();

        // Atualiza (ou cria) o vínculo principal de perfil/entidade do usuário.
        $puResp = $this->client()->get("/User/{$id}/Profile_User");
        $rows = ($puResp->successful() && is_array($puResp->json())) ? collect($puResp->json()) : collect();
        $primary = $rows->sortByDesc(fn ($r) => (! (bool) ($r['is_dynamic'] ?? false) ? 2 : 0) + ((int) ($r['entities_id'] ?? 0) !== 0 ? 1 : 0))->first();

        $puInput = [
            'users_id' => $id,
            'profiles_id' => $data['profile_id'],
            'entities_id' => $data['entity_id'],
            'is_recursive' => $data['recursive'] ? 1 : 0,
            'is_dynamic' => 0,
        ];

        if ($primary && ! (bool) ($primary['is_dynamic'] ?? false)) {
            $puInput['id'] = (int) $primary['id'];
            $this->client()->put('/Profile_User/'.(int) $primary['id'], ['input' => $puInput])->throw();
        } else {
            $this->client()->post('/Profile_User', ['input' => $puInput])->throw();
        }
        GlpiCache::flush('directory');
    }

    public function setUserActive(int $id, bool $active): void
    {
        $this->client()->put("/User/{$id}", ['input' => ['id' => $id, 'is_active' => $active ? 1 : 0]])->throw();
        GlpiCache::flush('directory');
    }

    // ----------------------------------------------------------------

    private function client(): PendingRequest
    {
        return GlpiCache::client($this->apiUrl, $this->appToken, $this->session(),
            fn () => $this->session(fresh: true));
    }

    /** Conta de serviço: token reaproveitado entre requisições (cache). */
    private function session(bool $fresh = false): string
    {
        if ($this->sessionToken !== null && ! $fresh) {
            return $this->sessionToken;
        }

        return $this->sessionToken = GlpiCache::serviceToken(
            $this->apiUrl, $this->user, $this->password, $this->appToken, fresh: $fresh,
        );
    }
}
