<?php

namespace App\Repositories\Glpi;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Inventário lido da API REST do GLPI. Usa o token do usuário logado quando
 * houver (isolamento por entidade nativo); senão a conta de serviço.
 */
class ApiGlpiInventoryRepository implements GlpiInventoryRepositoryInterface
{
    /** itemtype => [label, icon, campo do modelo]. */
    private const TYPES = [
        'Computer' => ['label' => 'Computadores', 'icon' => 'bi-pc-display', 'model' => 'computermodels_id'],
        'Monitor' => ['label' => 'Monitores', 'icon' => 'bi-display', 'model' => 'monitormodels_id'],
        'Printer' => ['label' => 'Impressoras', 'icon' => 'bi-printer', 'model' => 'printermodels_id'],
        'NetworkEquipment' => ['label' => 'Rede', 'icon' => 'bi-hdd-network', 'model' => 'networkequipmentmodels_id'],
        'Phone' => ['label' => 'Telefones', 'icon' => 'bi-telephone', 'model' => 'phonemodels_id'],
        'Peripheral' => ['label' => 'Periféricos', 'icon' => 'bi-usb-plug', 'model' => 'peripheralmodels_id'],
    ];

    private ?string $sessionToken = null;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $appToken,
        private readonly string $user,
        private readonly string $password,
    ) {
    }

    public function types(): array
    {
        return collect(self::TYPES)->map(fn ($c) => ['label' => $c['label'], 'icon' => $c['icon']])->all();
    }

    public function assets(): Collection
    {
        $out = collect();

        foreach (self::TYPES as $itemtype => $cfg) {
            $resp = $this->client()->get("/{$itemtype}", ['range' => '0-499', 'expand_dropdowns' => 'true']);
            if (! $resp->successful() || ! is_array($resp->json())) {
                continue;
            }

            foreach ($resp->json() as $a) {
                $out->push([
                    'id' => (int) ($a['id'] ?? 0),
                    'type' => $cfg['label'],
                    'typeKey' => $itemtype,
                    'icon' => $cfg['icon'],
                    'name' => (string) ($a['name'] ?? '(sem nome)'),
                    'entity' => $this->entityName($a['entities_id'] ?? null),
                    'status' => $this->val($a['states_id'] ?? null),
                    'serial' => $this->val($a['serial'] ?? null) ?: $this->val($a['otherserial'] ?? null),
                    'model' => $this->val($a[$cfg['model']] ?? null),
                    'manufacturer' => $this->val($a['manufacturers_id'] ?? null),
                    'location' => $this->val($a['locations_id'] ?? null),
                ]);
            }
        }

        return $out->sortBy([['type', 'asc'], ['name', 'asc']])->values();
    }

    /** Com expand_dropdowns, FKs viram nomes; 0/""/null = "—". */
    private function val(mixed $v): string
    {
        if ($v === null || $v === 0 || $v === '0' || $v === '') {
            return '—';
        }

        // GLPI devolve nomes com entidades HTML (ex.: "&#62;" = ">"); decodifica.
        return html_entity_decode((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /** Nome do cliente a partir do caminho da entidade (trecho após "CLIENTES"). */
    private function entityName(mixed $v): string
    {
        $name = $this->val($v);
        if ($name === '—') {
            return $name;
        }

        $parts = array_map('trim', explode('>', $name));
        $idx = array_search('CLIENTES', $parts, true);
        if ($idx !== false && isset($parts[$idx + 1])) {
            return implode(' › ', array_slice($parts, $idx + 1));
        }

        return end($parts) ?: $name;
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->apiUrl)
            ->acceptJson()
            ->withHeaders(array_filter([
                'Session-Token' => $this->session(),
                'App-Token' => $this->appToken ?: null,
            ]));
    }

    /**
     * Inventário usa a CONTA DE SERVIÇO (o perfil Self-Service do cliente não
     * tem direito de ler ativos) e ESCOPA a sessão na entidade do usuário
     * logado via changeActiveEntities — o GLPI então só devolve os ativos
     * daquela entidade (cliente vê só a sua; gestor vê CLIENTES recursivo).
     */
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
        $this->sessionToken = $token;

        $entity = session('glpi_entity');
        if ($entity !== null && $entity !== '') {
            Http::baseUrl($this->apiUrl)
                ->acceptJson()
                ->withHeaders(array_filter(['Session-Token' => $token, 'App-Token' => $this->appToken ?: null]))
                ->post('/changeActiveEntities', [
                    'entities_id' => (int) $entity,
                    'is_recursive' => (bool) session('glpi_entity_recursive'),
                ]);
        }

        return $this->sessionToken;
    }
}
