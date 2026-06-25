<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        // Os nomes batem de propósito com os dados do FakeGlpiTicketRepository,
        // para que cada painel já apareça povoado na demonstração.
        // glpi_id mapeia cada usuário do portal a um usuário do GLPI
        // (cliente -> post-only #3, técnico -> tech #4, gestor -> glpi #2).
        $users = [
            ['name' => 'Ana Cliente',   'email' => 'cliente@itsm.test', 'role' => UserRole::Cliente, 'glpi_id' => 3],
            ['name' => 'Tiago Técnico', 'email' => 'tecnico@itsm.test', 'role' => UserRole::Tecnico, 'glpi_id' => 4],
            ['name' => 'Gestor Demo',   'email' => 'gestor@itsm.test',  'role' => UserRole::Gestor,   'glpi_id' => 2],
        ];

        foreach ($users as $u) {
            User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'role' => $u['role'],
                    'glpi_id' => $u['glpi_id'],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
