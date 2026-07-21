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
            'Phone' => ['label' => 'Telefones', 'icon' => 'bi-telephone'],
            'Peripheral' => ['label' => 'Periféricos', 'icon' => 'bi-usb-plug'],
            'PluginGenericobjectDvr' => ['label' => 'DVRs', 'icon' => 'bi-camera-video'],
            'PluginGenericobjectAlarme' => ['label' => 'Alarmes', 'icon' => 'bi-bell'],
        ];
    }

    public function assets(): Collection
    {
        return collect([
            ['id' => 1, 'type' => 'Computadores', 'typeKey' => 'Computer', 'icon' => 'bi-pc-display', 'name' => 'PC-CAIXA-01', 'entity' => 'Drogacei > FL 01 - Setor O', 'status' => 'Em uso', 'serial' => 'SN-AB1234', 'model' => 'OptiPlex 3080', 'manufacturer' => 'Dell', 'location' => 'Balcão'],
            ['id' => 2, 'type' => 'Impressoras', 'typeKey' => 'Printer', 'icon' => 'bi-printer', 'name' => 'IMP-FISCAL-01', 'entity' => 'Drogacei > FL 01 - Setor O', 'status' => 'Em uso', 'serial' => 'PRN-9981', 'model' => 'Epson TM-T20', 'manufacturer' => 'Epson', 'location' => 'Caixa'],
            ['id' => 3, 'type' => 'Monitores', 'typeKey' => 'Monitor', 'icon' => 'bi-display', 'name' => 'MON-01', 'entity' => 'Mel do Sol', 'status' => 'Em uso', 'serial' => 'MON-5521', 'model' => 'E2220H', 'manufacturer' => 'Dell', 'location' => '—'],
        ]);
    }

    public function moveAsset(string $itemtype, int $id, int $entityId): void
    {
        // no-op (demo)
    }
}
