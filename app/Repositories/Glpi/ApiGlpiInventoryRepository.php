<?php

namespace App\Repositories\Glpi;

use Carbon\CarbonImmutable;
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
        'Peripheral' => ['label' => 'Periféricos', 'icon' => 'bi-usb-plug', 'model' => 'peripheralmodels_id'],
        // Ativos do plugin GenericObject (câmeras/segurança).
        'PluginGenericobjectDvr' => ['label' => 'DVRs', 'icon' => 'bi-camera-video', 'model' => 'plugin_genericobject_dvrmodels_id'],
        'PluginGenericobjectAlarme' => ['label' => 'Alarmes', 'icon' => 'bi-bell', 'model' => 'plugin_genericobject_alarmemodels_id'],
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

    public function moveAsset(string $itemtype, int $id, int $entityId): void
    {
        if (! isset(self::TYPES[$itemtype]) || $id <= 0 || $entityId <= 0) {
            throw new RuntimeException('Ativo ou entidade inválidos.');
        }

        // Move o ativo em si.
        $this->client()->put("/{$itemtype}/{$id}", [
            'input' => ['id' => $id, 'entities_id' => $entityId],
        ])->throw();

        // Mover um computador leva junto os itens conectados (monitor, impressora,
        // periférico, telefone). Editar só o campo entidade NÃO arrasta os
        // conectados no GLPI, então fazemos isso aqui — o que o inventário faria
        // por herança ao rodar o agent. Best-effort: falha num item não aborta.
        if ($itemtype === 'Computer') {
            $this->moveConnectedItems($id, $entityId);
        }
    }

    public function setInfocomValue(string $itemtype, int $id, ?float $value): void
    {
        if (! isset(self::TYPES[$itemtype]) || $id <= 0) {
            throw new RuntimeException('Ativo inválido.');
        }

        // Procura o Infocom (dados financeiros) existente do item.
        $resp = $this->client()->get("/{$itemtype}/{$id}/Infocom");
        $rows = ($resp->successful() && is_array($resp->json())) ? $resp->json() : [];
        $existing = $rows[0]['id'] ?? null;

        $input = ['itemtype' => $itemtype, 'items_id' => $id, 'value' => $value ?? 0];

        if ($existing) {
            $input['id'] = (int) $existing;
            $this->client()->put('/Infocom/'.(int) $existing, ['input' => $input])->throw();
        } else {
            $this->client()->post('/Infocom', ['input' => $input])->throw();
        }
    }

    public function assetDetails(string $itemtype, int $id): ?array
    {
        if (! isset(self::TYPES[$itemtype]) || $id <= 0) {
            return null;
        }

        // O próprio ativo (nome + campos base + datas). Se não vier (fora do escopo
        // da entidade ou inexistente), devolvemos null — o GLPI já isola por sessão.
        $resp = $this->client()->get("/{$itemtype}/{$id}", ['expand_dropdowns' => 'true']);
        if (! $resp->successful() || ! is_array($resp->json()) || empty($resp->json()['id'])) {
            return null;
        }
        $a = $resp->json();

        // Lista de campos (rótulo => valor) montada conforme o tipo. Só entra o que existe.
        $fields = [];
        $add = function (string $label, mixed $value) use (&$fields): void {
            $v = is_array($value) ? implode(' • ', array_filter($value)) : $this->val($value);
            if ($v !== '' && $v !== '—') {
                $fields[] = ['label' => $label, 'value' => $v];
            }
        };

        // Destaques técnicos por tipo.
        if ($itemtype === 'Computer') {
            $add('Processador', $this->deviceList("/Computer/{$id}/Item_DeviceProcessor", function (array $d): string {
                $name = $this->val($d['deviceprocessors_id'] ?? null);
                $freq = (int) ($d['frequency'] ?? 0);
                $cores = (int) ($d['nbcores'] ?? 0);
                $extra = array_filter([
                    $freq ? number_format($freq / 1000, 2, ',', '.').' GHz' : null,
                    $cores ? $cores.' núcleos' : null,
                ]);

                return trim(($name !== '—' ? $name : 'Processador').($extra ? ' — '.implode(', ', $extra) : ''));
            }));
            $add('Memória (RAM)', $this->ramSummary($id));
            $add('Disco(s)', $this->deviceList("/Computer/{$id}/Item_DeviceHardDrive", function (array $d): string {
                $cap = (int) ($d['capacity'] ?? 0);
                $name = $this->val($d['deviceharddrives_id'] ?? null);

                return trim(($cap ? $this->humanMB($cap) : '').($name !== '—' ? ' · '.$name : '')) ?: 'Disco';
            }));
            $add('Sistema operacional', $this->firstDevice("/Computer/{$id}/Item_OperatingSystem",
                fn (array $d): string => $this->val($d['operatingsystems_id'] ?? null)));
        } elseif ($itemtype === 'Monitor') {
            $size = (float) ($a['size'] ?? 0);
            $add('Tamanho', $size > 0 ? number_format($size, 0, ',', '.').'"' : null);
        } elseif ($itemtype === 'Printer') {
            $add('Memória', ! empty($a['memory_size']) ? ((int) $a['memory_size']).' MB' : null);
            $add('Conexões', array_filter([
                ! empty($a['have_usb']) ? 'USB' : null,
                ! empty($a['have_ethernet']) ? 'Ethernet' : null,
                ! empty($a['have_wifi']) ? 'Wi-Fi' : null,
                ! empty($a['have_serial']) ? 'Serial' : null,
            ]));
        } elseif ($itemtype === 'NetworkEquipment') {
            $add('Memória', ! empty($a['ram']) ? ((int) $a['ram']).' MB' : null);
            $add('MAC', $a['mac'] ?? null);
        }

        // Campos comuns a todos os tipos.
        $add('Fabricante', $a['manufacturers_id'] ?? null);
        $add('Modelo', $a[self::TYPES[$itemtype]['model']] ?? null);
        $add('Nº de série', $a['serial'] ?? null);
        $add('Nº patrimônio (GLPI)', $a['otherserial'] ?? null);
        $add('Localização', $a['locations_id'] ?? null);
        $add('Status', $a['states_id'] ?? null);
        $add('Contato', $a['contact'] ?? null);
        $add('Observações', $a['comment'] ?? null);

        return [
            'name' => (string) ($a['name'] ?? '(sem nome)'),
            'type' => self::TYPES[$itemtype]['label'],
            'fields' => $fields,
            'createdAt' => $this->fmtDate($a['date_creation'] ?? null),
            'updatedAt' => $this->fmtDate($a['date_mod'] ?? null),
        ];
    }

    /** Lê um sub-endpoint de dispositivos e mapeia cada linha para uma string. */
    private function deviceList(string $path, callable $fmt): array
    {
        $resp = $this->client()->get($path, ['expand_dropdowns' => 'true']);
        if (! $resp->successful() || ! is_array($resp->json())) {
            return [];
        }

        return collect($resp->json())->map($fmt)->filter()->values()->all();
    }

    /** Primeiro item de um sub-endpoint (ex.: sistema operacional). */
    private function firstDevice(string $path, callable $fmt): string
    {
        return $this->deviceList($path, $fmt)[0] ?? '—';
    }

    /** Soma os módulos de memória: "16 GB (2 módulos)". */
    private function ramSummary(int $id): string
    {
        $resp = $this->client()->get("/Computer/{$id}/Item_DeviceMemory", ['expand_dropdowns' => 'true']);
        if (! $resp->successful() || ! is_array($resp->json()) || $resp->json() === []) {
            return '—';
        }
        $mods = collect($resp->json());
        $totalMb = (int) $mods->sum(fn ($d) => (int) ($d['size'] ?? 0));
        $n = $mods->count();
        if ($totalMb <= 0) {
            return '—';
        }

        return $this->humanMB($totalMb).' ('.$n.' '.($n === 1 ? 'módulo' : 'módulos').')';
    }

    /** Capacidade em MB → "240 GB" / "1,00 TB". */
    private function humanMB(int $mb): string
    {
        if ($mb >= 1024 * 1024) {
            return number_format($mb / (1024 * 1024), 2, ',', '.').' TB';
        }
        if ($mb >= 1024) {
            return number_format($mb / 1024, ($mb % 1024 === 0 ? 0 : 1), ',', '.').' GB';
        }

        return $mb.' MB';
    }

    /** "2026-07-01 12:00:00" → "01/07/2026 12:00" (null se vazio). */
    private function fmtDate(?string $v): ?string
    {
        if (empty($v) || str_starts_with($v, '0000')) {
            return null;
        }

        try {
            return CarbonImmutable::parse($v)->format('d/m/Y H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /** Move os itens conectados a um computador para a mesma entidade. */
    private function moveConnectedItems(int $computerId, int $entityId): void
    {
        $resp = $this->client()->get("/Computer/{$computerId}/Computer_Item");
        if (! $resp->successful() || ! is_array($resp->json())) {
            return;
        }

        $connectable = ['Monitor', 'Printer', 'Peripheral', 'Phone'];
        foreach ($resp->json() as $link) {
            $type = (string) ($link['itemtype'] ?? '');
            $iid = (int) ($link['items_id'] ?? 0);
            if ($iid > 0 && in_array($type, $connectable, true)) {
                // Sem ->throw(): item global/compartilhado pode recusar; seguimos.
                $this->client()->put("/{$type}/{$iid}", [
                    'input' => ['id' => $iid, 'entities_id' => $entityId],
                ]);
            }
        }
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

        // A conta de serviço é ampla (vê todas as entidades). É OBRIGATÓRIO
        // escopar na entidade do usuário logado; sem isso NÃO expomos ativos
        // (fail-closed) — evita um cliente enxergar ativos de outro/da raiz.
        //
        // Fail-closed também quando o escopo é a RAIZ sem recursividade: isso é
        // sintoma de usuário mal atribuído (ex.: cliente na "Entidade raiz"),
        // que senão veria os ativos da raiz. Retornamos '' → sem token → o GLPI
        // recusa e a lista vem vazia, sem vazamento. (Gestor legítimo na raiz é
        // recursivo, então não cai aqui.)
        $entity = session('glpi_entity');
        $recursive = (bool) session('glpi_entity_recursive');
        if ($entity === null || $entity === '' || ((int) $entity === 0 && ! $recursive)) {
            return '';
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

        $scoped = Http::baseUrl($this->apiUrl)
            ->acceptJson()
            ->withHeaders(array_filter(['Session-Token' => $token, 'App-Token' => $this->appToken ?: null]))
            ->post('/changeActiveEntities', [
                'entities_id' => (int) $entity,
                'is_recursive' => (bool) session('glpi_entity_recursive'),
            ]);
        if (! $scoped->successful()) {
            throw new RuntimeException('Falha ao aplicar o isolamento por entidade no GLPI.');
        }

        $this->sessionToken = $token;

        return $this->sessionToken;
    }
}
