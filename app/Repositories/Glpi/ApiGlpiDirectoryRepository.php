<?php

namespace App\Repositories\Glpi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

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

    public function entities(): Collection
    {
        $resp = $this->client()->get('/Entity', ['range' => '0-999', 'expand_dropdowns' => 'true']);
        if (! $resp->successful() || ! is_array($resp->json())) {
            return collect();
        }

        return collect($resp->json())
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
        $resp = $this->client()->get('/Profile', ['range' => '0-200']);
        if (! $resp->successful() || ! is_array($resp->json())) {
            return collect();
        }

        return collect($resp->json())
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
        $usersResp = $this->client()->get('/User', ['range' => '0-999']);
        $puResp = $this->client()->get('/Profile_User', ['range' => '0-999']);
        if (! $usersResp->successful() || ! is_array($usersResp->json())) {
            return collect();
        }

        $entityNames = $this->entities()->keyBy('id')->map(fn ($e) => $e['completename']);
        $profileNames = $this->profiles()->keyBy('id')->map(fn ($p) => $p['name']);

        // Agrupa Profile_User por usuário e escolhe o vínculo PRINCIPAL:
        // não-dinâmico e fora da raiz; senão não-dinâmico; senão o primeiro.
        $byUser = collect(is_array($puResp->json()) ? $puResp->json() : [])->groupBy('users_id');
        $primary = function (int $uid) use ($byUser) {
            $rows = $byUser->get($uid, collect());
            if ($rows instanceof Collection === false) {
                $rows = collect($rows);
            }

            return $rows->sortByDesc(fn ($r) => (! (bool) ($r['is_dynamic'] ?? false) ? 2 : 0) + ((int) ($r['entities_id'] ?? 0) !== 0 ? 1 : 0))->first();
        };

        return collect($usersResp->json())
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

        return (int) ($resp->json('id') ?? $resp->json('0.id'));
    }

    public function updateEntity(int $id, string $name): void
    {
        $this->client()->put("/Entity/{$id}", ['input' => ['id' => $id, 'name' => $name]])->throw();
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
    }

    public function setUserActive(int $id, bool $active): void
    {
        $this->client()->put("/User/{$id}", ['input' => ['id' => $id, 'is_active' => $active ? 1 : 0]])->throw();
    }

    // ----------------------------------------------------------------

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->apiUrl)
            ->acceptJson()
            ->withHeaders(array_filter([
                'Session-Token' => $this->session(),
                'App-Token' => $this->appToken ?: null,
            ]));
    }

    private function session(): string
    {
        if ($this->sessionToken !== null) {
            return $this->sessionToken;
        }

        $resp = Http::baseUrl($this->apiUrl)
            ->acceptJson()
            ->withBasicAuth($this->user, $this->password)
            ->withHeaders(array_filter(['App-Token' => $this->appToken ?: null]))
            ->get('/initSession');
        $resp->throw();

        $token = $resp->json('session_token');
        if (! $token) {
            throw new RuntimeException('GLPI initSession não retornou session_token.');
        }

        return $this->sessionToken = $token;
    }
}
