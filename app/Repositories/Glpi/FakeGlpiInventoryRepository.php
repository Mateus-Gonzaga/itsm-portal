<?php

namespace App\Repositories\Glpi;

use Illuminate\Support\Collection;

/** Inventário de demonstração (modo fake), sem tocar no GLPI. */
class FakeGlpiInventoryRepository implements GlpiInventoryRepositoryInterface
{
    public function types(): array
    {
        return [
            'Computer' => ['label' => 'Computadores', 'icon' => 'bi-pc-display'],
            'Monitor' => ['label' => 'Monitores', 'icon' => 'bi-display'],
            'Printer' => ['label' => 'Impressoras', 'icon' => 'bi-printer'],
            'NetworkEquipment' => ['label' => 'Rede', 'icon' => 'bi-hdd-network'],
            'Peripheral' => ['label' => 'Periféricos', 'icon' => 'bi-usb-plug'],
            'PluginGenericobjectDvr' => ['label' => 'DVRs', 'icon' => 'bi-camera-video'],
            'PluginGenericobjectAlarme' => ['label' => 'Alarmes', 'icon' => 'bi-bell'],
        ];
    }

    public function assets(): Collection
    {
        return collect([
            ['id' => 1, 'type' => 'Computadores', 'typeKey' => 'Computer', 'icon' => 'bi-pc-display', 'name' => 'PC-CAIXA-01', 'entity' => 'Drogacei > FL 01 - Setor O', 'status' => 'Em uso', 'serial' => 'SN-AB1234', 'model' => 'OptiPlex 3080', 'manufacturer' => 'Dell', 'location' => 'Balcão', 'created' => '01/07/2026 09:30'],
            ['id' => 2, 'type' => 'Impressoras', 'typeKey' => 'Printer', 'icon' => 'bi-printer', 'name' => 'IMP-FISCAL-01', 'entity' => 'Drogacei > FL 01 - Setor O', 'status' => 'Em uso', 'serial' => 'PRN-9981', 'model' => 'Epson TM-T20', 'manufacturer' => 'Epson', 'location' => 'Caixa', 'created' => '02/07/2026 14:10'],
            ['id' => 3, 'type' => 'Monitores', 'typeKey' => 'Monitor', 'icon' => 'bi-display', 'name' => 'MON-01', 'entity' => 'Mel do Sol', 'status' => 'Em uso', 'serial' => 'MON-5521', 'model' => 'E2220H', 'manufacturer' => 'Dell', 'location' => '—', 'created' => '03/07/2026 08:05'],
            ['id' => 4, 'type' => 'DVRs', 'typeKey' => 'PluginGenericobjectDvr', 'icon' => 'bi-camera-video', 'name' => 'DVR-CFTV-01', 'entity' => 'Drogacei > FL 01 - Setor O', 'status' => 'Em uso', 'serial' => 'DVR-INTEL-88', 'model' => 'MHDX 1116', 'manufacturer' => 'Intelbras', 'location' => 'CPD', 'created' => '05/07/2026 11:20'],
            ['id' => 5, 'type' => 'Impressoras', 'typeKey' => 'Printer', 'icon' => 'bi-printer', 'name' => 'IMP-LASER-ADM', 'entity' => 'Drogacei > Matriz', 'status' => 'Em uso', 'serial' => 'PRN-HP-404', 'model' => 'LaserJet Pro M404', 'manufacturer' => 'HP', 'location' => 'Escritório', 'created' => '06/07/2026 15:45'],
            ['id' => 6, 'type' => 'Computadores', 'typeKey' => 'Computer', 'icon' => 'bi-pc-display', 'name' => 'PC-FINANCEIRO', 'entity' => 'A2 Arquitetura', 'status' => 'Em uso', 'serial' => 'SN-LEN-99', 'model' => 'ThinkCentre M70q', 'manufacturer' => 'Lenovo', 'location' => 'Administrativo', 'created' => '08/07/2026 10:15'],
        ]);
    }

    public function createAsset(string $itemtype, array $data): int
    {
        return random_int(9000, 9999); // demo
    }

    public function moveAsset(string $itemtype, int $id, int $entityId): void
    {
        // no-op (demo)
    }

    public function deleteAsset(string $itemtype, int $id): void
    {
        // no-op (demo)
    }

    public function setInfocomValue(string $itemtype, int $id, ?float $value): void
    {
        // no-op (demo)
    }

    public function assetDetails(string $itemtype, int $id): ?array
    {
        $fields = $itemtype === 'Computer'
            ? [
                ['label' => 'Processador', 'value' => 'Intel Core i5-8400 — 2,80 GHz, 6 núcleos'],
                ['label' => 'Memória (RAM)', 'value' => '16 GB (2 módulos)'],
                ['label' => 'Disco(s)', 'value' => '240 GB · SSD'],
                ['label' => 'Sistema operacional', 'value' => 'Windows 10 Pro'],
                ['label' => 'Marca', 'value' => 'Dell'],
            ]
            : [
                ['label' => 'Marca', 'value' => 'Genérico'],
                ['label' => 'Modelo', 'value' => 'Demo '.$itemtype],
                ['label' => 'Nº de série', 'value' => 'SN-'.$id],
            ];

        return [
            'name' => 'ATIVO-DEMO-'.$id,
            'type' => $itemtype,
            'fields' => $fields,
            'createdAt' => '01/07/2026 09:30',
            'updatedAt' => now()->format('d/m/Y H:i'),
        ];
    }
}
