<?php

namespace Tests\Feature;

use App\Repositories\Glpi\FakeGlpiDirectoryRepository;
use App\Repositories\Glpi\FakeGlpiPlanningRepository;
use App\Repositories\Glpi\FakeGlpiTicketRepository;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/** Contrato dos repositórios (driver Fake) — protege a lógica de filtro/CRUD. */
class RepositoryContractTest extends TestCase
{
    public function test_tickets_filtram_por_solicitante_e_persistem(): void
    {
        $r = new FakeGlpiTicketRepository();

        $todos = $r->all();
        $this->assertGreaterThan(0, $todos->count());

        // Filtro por solicitante só devolve chamados daquele solicitante.
        $daAna = $r->all(['requester' => 'Ana Cliente']);
        $this->assertGreaterThan(0, $daAna->count());
        $this->assertTrue($daAna->every(fn ($t) => $t->requesterName === 'Ana Cliente'));

        // Criar persiste e é recuperável por find().
        $novo = $r->create(['title' => 'Teste contrato', 'description' => 'x', 'priority' => 'high', 'type' => 'incident', 'requester' => 'Zé Teste']);
        $this->assertSame('Teste contrato', $r->find($novo->id)?->title);
    }

    public function test_agenda_agenda_e_remarca(): void
    {
        $r = new FakeGlpiPlanningRepository();

        $antes = $r->events()->where('type', 'task')->count();
        $r->schedule(99, 4, CarbonImmutable::parse('2026-07-01 09:00'), CarbonImmutable::parse('2026-07-01 10:00'), 'x');
        $this->assertSame($antes + 1, $r->events()->where('type', 'task')->count());

        // Filtro por técnico devolve só tarefas daquele técnico.
        $doTec = $r->events(['technician_glpi_id' => 4])->where('type', 'task');
        $this->assertTrue($doTec->every(fn ($e) => $e->technicianId === 4));
    }

    public function test_diretorio_lista_entidades_usuarios_perfis(): void
    {
        $d = new FakeGlpiDirectoryRepository();

        $this->assertGreaterThan(0, $d->entities()->count());
        $this->assertGreaterThan(0, $d->users()->count());
        $this->assertTrue($d->profiles()->contains(fn ($p) => $p['name'] === 'Técnico FL'));
    }
}
