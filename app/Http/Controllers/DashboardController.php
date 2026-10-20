<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\UserRole;
use App\Models\KanbanCard;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use App\Support\ClientName;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, GlpiTicketRepositoryInterface $tickets): View
    {
        $user = $request->user();

        // Cada perfil enxerga um recorte diferente dos chamados.
        // Passa nome (driver Fake) + glpi_id (driver Api filtra pelos atores).
        $list = match ($user->role) {
            UserRole::Cliente => $tickets->all(array_filter([
                'requester' => $user->name,
                'requester_glpi_id' => $user->glpi_id,
            ], fn ($v) => $v !== null)),
            UserRole::Tecnico => $tickets->all(array_filter([
                'technician' => $user->name,
                'technician_glpi_id' => $user->glpi_id,
            ], fn ($v) => $v !== null)),
            UserRole::Gestor => $tickets->all(),
        };

        $closed = [TicketStatus::Solved, TicketStatus::Closed];
        $total = $list->count();
        $solved = $list->filter(fn (TicketData $t) => in_array($t->status, $closed, true))->count();

        $metrics = [
            'total' => $total,
            'open' => $list->reject(fn (TicketData $t) => in_array($t->status, $closed, true))->count(),
            'overdue' => $list->filter(fn (TicketData $t) => $t->isOverdue())->count(),
            'solved' => $solved,
            'resolution' => $total > 0 ? (int) round($solved / $total * 100) : 0,
        ];

        $subtitle = match ($user->role) {
            UserRole::Cliente => 'Aqui está um resumo dos seus chamados',
            UserRole::Tecnico => 'Aqui está o seu painel de atendimento',
            UserRole::Gestor => 'Visão geral completa do atendimento',
        };

        // Distribuições por status/prioridade (só o que tem contagem > 0).
        $byStatus = collect(TicketStatus::cases())
            ->map(fn (TicketStatus $s) => ['label' => $s->label(), 'color' => $s->color(), 'count' => $list->where('status', $s)->count()])
            ->filter(fn ($r) => $r['count'] > 0)->values();

        $byPriority = collect(TicketPriority::cases())
            ->map(fn (TicketPriority $p) => ['label' => $p->label(), 'color' => $p->color(), 'count' => $list->where('priority', $p)->count()])
            ->filter(fn ($r) => $r['count'] > 0)->values();

        // Próximos prazos (chamados abertos com data-limite, mais urgentes primeiro).
        $upcoming = $list
            ->filter(fn (TicketData $t) => ! in_array($t->status, $closed, true) && $t->dueDate !== null)
            ->sortBy(fn (TicketData $t) => $t->dueDate)
            ->take(5)
            ->values();

        // Resumo do quadro Kanban da equipe (staff).
        $kanban = $user->role === UserRole::Cliente
            ? collect()
            : KanbanCard::selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return view('dashboard.index', [
            'charts' => $user->role === UserRole::Cliente ? null : $this->charts($list),
            'role' => $user->role,
            'tickets' => $list,
            'metrics' => $metrics,
            'subtitle' => $subtitle,
            'byStatus' => $byStatus,
            'byPriority' => $byPriority,
            'upcoming' => $upcoming,
            'kanban' => $kanban,
        ]);
    }

    /**
     * Indicadores para os gráficos do painel (equipe): chamados por cliente,
     * abertos × resolvidos por mês, tempo médio de solução e SLA cumprido.
     */
    private function charts(\Illuminate\Support\Collection $list): array
    {
        $now = CarbonImmutable::now();
        $months = collect(range(5, 0))->map(fn (int $i) => $now->startOfMonth()->subMonths($i));

        // Chamados por cliente (top 10, últimos 6 meses).
        $since = $months->first();
        $recent = $list->filter(fn (TicketData $t) => $t->createdAt->gte($since));
        // Mesmo agrupamento dos Relatórios: por empresa (não por filial/pasta).
        $byClient = $recent->groupBy(fn (TicketData $t) => ClientName::company($t->entity))
            ->map->count()->sortDesc()->take(10);

        // Abertos × resolvidos por mês + tempo médio de solução (horas) por mês.
        $opened = [];
        $solved = [];
        $avgHours = [];
        foreach ($months as $m) {
            $end = $m->endOfMonth();
            $opened[] = $list->filter(fn (TicketData $t) => $t->createdAt->between($m, $end))->count();
            $solvedInMonth = $list->filter(fn (TicketData $t) => $t->solvedAt !== null && $t->solvedAt->between($m, $end));
            $solved[] = $solvedInMonth->count();
            // Mês sem chamado resolvido = sem dado (não "0 h", que distorce a linha).
            $avgHours[] = $solvedInMonth->isEmpty() ? null
                : round($solvedInMonth->avg(fn (TicketData $t) => max(0, $t->createdAt->diffInMinutes($t->solvedAt)) / 60), 1);
        }

        // SLA: dos resolvidos com prazo, quantos dentro do prazo (últimos 6 meses).
        $withSla = $list->filter(fn (TicketData $t) => $t->solvedAt !== null && $t->dueDate !== null && $t->solvedAt->gte($since));
        $slaOk = $withSla->filter(fn (TicketData $t) => $t->solvedAt->lte($t->dueDate))->count();
        $slaLate = $withSla->count() - $slaOk;

        $solvedAll = $list->filter(fn (TicketData $t) => $t->solvedAt !== null && $t->solvedAt->gte($since));
        $avgAll = $solvedAll->isEmpty() ? null
            : $solvedAll->avg(fn (TicketData $t) => max(0, $t->createdAt->diffInMinutes($t->solvedAt)) / 60);

        return [
            'months' => $months->map(fn ($m) => $m->locale('pt_BR')->translatedFormat('M/y'))->values()->all(),
            'opened' => $opened,
            'solved' => $solved,
            'avgHours' => $avgHours,
            'clients' => $byClient->map(fn ($n, $nome) => ['nome' => (string) $nome, 'total' => $n])->values()->all(),
            'sla' => ['ok' => $slaOk, 'late' => $slaLate,
                'pct' => $withSla->count() > 0 ? (int) round($slaOk / $withSla->count() * 100) : null],
            'avgAll' => $avgAll === null ? null : $this->humanHours($avgAll),
        ];
    }

    private function humanHours(float $hours): string
    {
        if ($hours < 1) {
            return max(1, (int) round($hours * 60)).' min';
        }
        if ($hours < 48) {
            return number_format($hours, 1, ',', '.').' h';
        }

        return number_format($hours / 24, 1, ',', '.').' dias';
    }
}
