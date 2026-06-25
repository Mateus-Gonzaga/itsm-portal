<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trava o controle de acesso por papel (multi-tenant) — protege contra regressões.
 * Roda com os drivers Fake (GLPI/Zabbix) e SQLite em memória (não toca o dev).
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function user(UserRole $role): User
    {
        return User::factory()->create([
            'role' => $role,
            'glpi_id' => 21,
        ]);
    }

    public function test_cliente_acessa_proprias_areas_e_e_bloqueado_nas_do_gestor(): void
    {
        $u = $this->user(UserRole::Cliente);

        $this->actingAs($u)->get('/dashboard')->assertOk();
        $this->actingAs($u)->get('/tickets')->assertOk();

        foreach (['/clientes', '/tecnicos', '/relatorios', '/dashboard-analitico', '/agenda'] as $rota) {
            $this->actingAs($u)->get($rota)->assertForbidden();
        }
    }

    public function test_tecnico_acessa_agenda_mas_nao_areas_do_gestor(): void
    {
        $u = $this->user(UserRole::Tecnico);

        $this->actingAs($u)->get('/agenda')->assertOk();
        $this->actingAs($u)->get('/tickets')->assertOk();

        foreach (['/clientes', '/tecnicos', '/relatorios', '/dashboard-analitico'] as $rota) {
            $this->actingAs($u)->get($rota)->assertForbidden();
        }
    }

    public function test_gestor_acessa_todas_as_areas(): void
    {
        $u = $this->user(UserRole::Gestor);

        foreach (['/dashboard', '/clientes', '/tecnicos', '/relatorios', '/dashboard-analitico', '/agenda'] as $rota) {
            $this->actingAs($u)->get($rota)->assertOk();
        }
    }

    public function test_visitante_e_redirecionado_para_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_gestor_nao_pode_atribuir_perfil_proibido_anti_escalonamento(): void
    {
        $u = $this->user(UserRole::Gestor);
        $base = [
            'login' => 'teste.user',
            'name' => 'Teste',
            'password' => 'segredo123',
            'entity_id' => 2, // Drogacei (sob CLIENTES) — permitido no Fake
        ];

        // Perfil fora da lista branca (ex.: Super-Admin id 4) -> 403.
        $this->actingAs($u)->post('/diretorio/usuarios', array_merge($base, ['profile_id' => 4]))->assertForbidden();

        // Entidade fora de CLIENTES (raiz id 0) -> 403.
        $this->actingAs($u)->post('/diretorio/usuarios', array_merge($base, ['profile_id' => 1, 'entity_id' => 0]))->assertForbidden();

        // Perfil permitido (Self-Service id 1) + entidade válida -> aceito (redirect back).
        $this->actingAs($u)->post('/diretorio/usuarios', array_merge($base, ['profile_id' => 1]))->assertRedirect();
    }
}
