<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\KanbanCard;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use Illuminate\Database\Seeder;

/**
 * Popula o quadro "Atenção / Urgente" com as demandas do joão.
 * Rodar: php artisan db:seed --class=QuadroUrgenteSeeder --force
 * Idempotente (updateOrCreate pelo board+título).
 */
class QuadroUrgenteSeeder extends Seeder
{
    public function run(GlpiDirectoryRepositoryInterface $dir): void
    {
        // Descobre o glpi_id do joão (para atribuir os cartões a ele).
        $joao = $dir->users()->first(fn (array $u) => str_contains(
            mb_strtolower((string) ($u['login'] ?? '')), 'joao.fourline'
        ));
        $ownerId = $joao['id'] ?? null;
        $ownerName = $joao['name'] ?? 'joao.fourline';

        $demandas = [
            'E-mail da Mel do Sol',
            'Terminar de subir os monitoramentos nos nossos clientes',
            'Fazer as viradas de rede da Fátima',
            'Retirar o cabo da FL 09 do chão',
            'Hack do Rpaz para colocar',
            'Hack da Recplan DF para colocar (com mais tempo)',
            'Revisar todos os acessos de câmera da Cei M (já feito em algumas lojas)',
        ];

        $pos = 0;
        foreach ($demandas as $titulo) {
            KanbanCard::updateOrCreate(
                ['board' => 'urgente', 'title' => $titulo],
                [
                    'status' => 'todo',
                    'color' => '#dc3545',
                    'assignee_glpi_id' => $ownerId,
                    'assignee_name' => $ownerName,
                    'position' => $pos++,
                ],
            );
        }
    }
}
