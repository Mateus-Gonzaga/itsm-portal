<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Relatórios, inventário paginado, painel com gráficos e /saude — com os
 * drivers Fake (GLPI/Zabbix) e SQLite em memória.
 */
class ReportsAndPagesTest extends TestCase
{
    use RefreshDatabase;

    private function gestor(): User
    {
        return User::factory()->create(['role' => UserRole::Gestor, 'glpi_id' => 21]);
    }

    public function test_todas_as_abas_de_relatorio_abrem(): void
    {
        $u = $this->gestor();
        foreach (['geral', 'clientes', 'tecnicos', 'categorias', 'chamados', 'marcas'] as $aba) {
            $this->actingAs($u)->get('/relatorios?aba='.$aba.'&periodo=tudo')->assertOk()->assertSee('Relatórios');
        }
        // Período personalizado e filtros combinados.
        $this->actingAs($u)->get('/relatorios?periodo=personalizado&de=2026-01-01&ate=2026-12-31&situacao=abertos')->assertOk();
    }

    public function test_exporta_csv_e_relatorio_do_cliente(): void
    {
        $u = $this->gestor();

        $csv = $this->actingAs($u)->get('/relatorios/exportar?tipo=chamados&periodo=tudo');
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $csv->headers->get('Content-Type'));

        foreach (['clientes', 'tecnicos', 'categorias'] as $tipo) {
            $this->actingAs($u)->get('/relatorios/exportar?tipo='.$tipo.'&periodo=tudo')->assertOk();
        }

        $this->actingAs($u)->get('/relatorios/cliente?periodo=tudo&cliente=Drogaria%20Centro')
            ->assertOk()->assertSee('Drogaria Centro');
        $this->actingAs($u)->get('/relatorios/cliente')->assertNotFound();
    }

    public function test_relatorios_bloqueados_para_cliente(): void
    {
        $u = User::factory()->create(['role' => UserRole::Cliente, 'glpi_id' => 21]);
        $this->actingAs($u)->get('/relatorios/exportar')->assertForbidden();
        $this->actingAs($u)->get('/relatorios/cliente?cliente=X')->assertForbidden();
    }

    public function test_inventario_paginado_e_filtrado(): void
    {
        $u = $this->gestor();
        $this->actingAs($u)->get('/inventario')->assertOk();
        $this->actingAs($u)->get('/inventario?q=PC&tipo=Computadores&page=1')->assertOk();
    }

    public function test_painel_com_graficos_e_abrir_chamado_por_equipamento(): void
    {
        $u = $this->gestor();
        $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('chMonthly', false);
        $this->actingAs($u)->get('/tickets/create?ativo=Computer:1')->assertOk();
    }

    public function test_saude_exige_token(): void
    {
        config(['portal.health_token' => '']);
        $this->get('/saude')->assertNotFound();

        config(['portal.health_token' => 'abc']);
        $this->get('/saude?token=errado')->assertNotFound();
        $this->get('/saude?token=abc')->assertOk()->assertJsonStructure(['db', 'cache', 'glpi', 'ok', 'disco_livre_pct']);
    }
}
