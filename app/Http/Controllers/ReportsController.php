<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Models\AssetValue;
use App\Repositories\Glpi\GlpiInventoryRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Relatórios gerenciais (gestor): filtros por período/cliente/técnico/categoria/
 * situação, visões por cliente, técnico e categoria com tempo médio de solução
 * e SLA, lista de chamados, marcas do inventário, exportação CSV (Excel) e
 * relatório do cliente para impressão/PDF.
 */
class ReportsController extends Controller
{
    private const ABAS = ['geral', 'clientes', 'tecnicos', 'categorias', 'chamados', 'marcas'];

    public function __invoke(Request $request, GlpiTicketRepositoryInterface $tickets, GlpiInventoryRepositoryInterface $inventory): View
    {
        $f = $this->filters($request);
        $all = $tickets->all();
        $list = $this->apply($all, $f);
        $aba = in_array($request->query('aba'), self::ABAS, true) ? (string) $request->query('aba') : 'geral';

        $data = [
            'f' => $f,
            'aba' => $aba,
            'opcoes' => $this->options($all),
            'kpi' => $this->stats($list),
        ];

        // Cada aba só calcula o que mostra.
        switch ($aba) {
            case 'geral':
                $data['byStatus'] = collect(TicketStatus::cases())
                    ->map(fn (TicketStatus $s) => ['label' => $s->label(), 'color' => $s->color(), 'count' => $list->where('status', $s)->count()])
                    ->filter(fn ($r) => $r['count'] > 0)->values();
                $data['byPriority'] = collect(TicketPriority::cases())
                    ->map(fn (TicketPriority $p) => ['label' => $p->label(), 'color' => $p->color(), 'count' => $list->where('priority', $p)->count()])
                    ->filter(fn ($r) => $r['count'] > 0)->values();
                $data['byType'] = collect(TicketType::cases())
                    ->map(fn (TicketType $t) => ['label' => $t->label(), 'count' => $list->where('type', $t)->count()])
                    ->filter(fn ($r) => $r['count'] > 0)->values();
                $data['serie'] = $this->series($list, $f);
                $data['topClientes'] = $this->groupStats($list, fn (TicketData $t) => $this->company($t))->take(8)->values();
                break;
            case 'clientes':
                $data['rows'] = $this->groupStats($list, fn (TicketData $t) => $this->company($t));
                break;
            case 'tecnicos':
                $data['rows'] = $this->groupStats($list, fn (TicketData $t) => $t->technicianName ?: 'Não atribuído');
                break;
            case 'categorias':
                $data['rows'] = $this->groupStats($list, fn (TicketData $t) => $t->category ?: 'Sem categoria');
                break;
            case 'chamados':
                $page = max(1, (int) $request->query('page', 1));
                $data['chamados'] = new LengthAwarePaginator(
                    $list->forPage($page, 50)->values(), $list->count(), 50, $page,
                    ['path' => $request->url(), 'query' => $request->except('page')],
                );
                break;
            case 'marcas':
                $data += $this->brands($inventory);
                break;
        }

        return view('modules.reports', $data);
    }

    /** Exporta a visão escolhida em CSV (abre no Excel). */
    public function export(Request $request, GlpiTicketRepositoryInterface $tickets): StreamedResponse
    {
        $f = $this->filters($request);
        $list = $this->apply($tickets->all(), $f);
        $tipo = in_array($request->query('tipo'), ['chamados', 'clientes', 'tecnicos', 'categorias'], true) ? (string) $request->query('tipo') : 'chamados';

        if ($tipo === 'chamados') {
            $header = ['Nº', 'Título', 'Cliente', 'Filial', 'Solicitante', 'Técnico', 'Categoria', 'Tipo', 'Prioridade', 'Situação',
                'Aberto em', 'Prazo (SLA)', 'Solucionado em', 'Tempo de solução (h)', 'SLA'];
            $rows = $list->map(fn (TicketData $t) => [
                $t->id, $t->title, $this->company($t), $this->branch($t), $t->requesterName, $t->technicianName ?: 'Não atribuído',
                $t->category ?: '', $t->type->label(), $t->priority->label(), $t->status->label(),
                $t->createdAt->format('d/m/Y H:i'), $t->dueDate?->format('d/m/Y H:i') ?? '', $t->solvedAt?->format('d/m/Y H:i') ?? '',
                $t->solvedAt ? $this->num(max(0, $t->createdAt->diffInMinutes($t->solvedAt)) / 60) : '',
                $this->slaLabel($t),
            ]);
        } else {
            $label = ['clientes' => 'Cliente', 'tecnicos' => 'Técnico', 'categorias' => 'Categoria'][$tipo];
            $key = match ($tipo) {
                'clientes' => fn (TicketData $t) => $this->company($t),
                'tecnicos' => fn (TicketData $t) => $t->technicianName ?: 'Não atribuído',
                default => fn (TicketData $t) => $t->category ?: 'Sem categoria',
            };
            $header = [$label, 'Total', 'Em aberto', 'Resolvidos', 'Atrasados', '% resolvidos', 'Tempo médio de solução (h)', 'SLA cumprido (%)'];
            $rows = $this->groupStats($list, $key)->map(fn (array $r) => [
                $r['nome'], $r['total'], $r['abertos'], $r['resolvidos'], $r['atrasados'], $r['resolucao'],
                $r['tempo_h'] === null ? '' : $this->num($r['tempo_h']), $r['sla'] ?? '',
            ]);
        }

        $file = 'relatorio-'.$tipo.'-'.$f['de']->format('Ymd').'-'.$f['ate']->format('Ymd').'.csv';

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: acentos certos no Excel
            fputcsv($out, $header, ';');
            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Relatório do cliente para imprimir/salvar em PDF e enviar a ele. */
    public function client(Request $request, GlpiTicketRepositoryInterface $tickets): View
    {
        $f = $this->filters($request);
        abort_if($f['cliente'] === '', 404, 'Escolha um cliente.');
        $list = $this->apply($tickets->all(), $f);

        return view('modules.reports-client', [
            'f' => $f,
            'kpi' => $this->stats($list),
            'chamados' => $list->sortBy(fn (TicketData $t) => $t->createdAt->getTimestamp())->values(),
            'porFilial' => $this->groupStats($list, fn (TicketData $t) => $this->branch($t) ?: $this->company($t)),
            'porCategoria' => $this->groupStats($list, fn (TicketData $t) => $t->category ?: 'Sem categoria'),
            'geradoPor' => $request->user()->name,
            'slaLabel' => fn (TicketData $t) => $this->slaLabel($t),
            'filial' => fn (TicketData $t) => $this->branch($t),
        ]);
    }

    // ------------------------------------------------------------------ filtros

    /** @return array{de:CarbonImmutable, ate:CarbonImmutable, periodo:string, cliente:string, tecnico:string, categoria:string, situacao:string} */
    private function filters(Request $request): array
    {
        $now = CarbonImmutable::now();
        $periodo = (string) $request->query('periodo', '30d');
        if (! in_array($periodo, ['30d', 'mes', 'mes_passado', '90d', 'ano', 'tudo', 'personalizado'], true)) {
            $periodo = '30d';
        }

        [$de, $ate] = match ($periodo) {
            'mes' => [$now->startOfMonth(), $now->endOfDay()],
            'mes_passado' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            '90d' => [$now->subDays(89)->startOfDay(), $now->endOfDay()],
            'ano' => [$now->startOfYear(), $now->endOfDay()],
            'tudo' => [CarbonImmutable::create(2000, 1, 1), $now->endOfDay()],
            'personalizado' => [
                $this->date($request->query('de'))?->startOfDay() ?? $now->subDays(29)->startOfDay(),
                $this->date($request->query('ate'))?->endOfDay() ?? $now->endOfDay(),
            ],
            default => [$now->subDays(29)->startOfDay(), $now->endOfDay()],
        };
        if ($de->gt($ate)) {
            [$de, $ate] = [$ate->startOfDay(), $de->endOfDay()];
        }

        return [
            'de' => $de,
            'ate' => $ate,
            'periodo' => $periodo,
            'cliente' => trim((string) $request->query('cliente', '')),
            'tecnico' => trim((string) $request->query('tecnico', '')),
            'categoria' => trim((string) $request->query('categoria', '')),
            'situacao' => in_array($request->query('situacao'), ['abertos', 'resolvidos', 'atrasados'], true) ? (string) $request->query('situacao') : '',
        ];
    }

    private function apply(Collection $all, array $f): Collection
    {
        $closed = [TicketStatus::Solved, TicketStatus::Closed];

        return $all
            ->filter(fn (TicketData $t) => $t->createdAt->between($f['de'], $f['ate']))
            ->when($f['cliente'] !== '', fn ($c) => $c->filter(fn (TicketData $t) => $this->company($t) === $f['cliente']))
            ->when($f['tecnico'] !== '', fn ($c) => $c->filter(fn (TicketData $t) => ($t->technicianName ?: 'Não atribuído') === $f['tecnico']))
            ->when($f['categoria'] !== '', fn ($c) => $c->filter(fn (TicketData $t) => ($t->category ?: 'Sem categoria') === $f['categoria']))
            ->when($f['situacao'] === 'abertos', fn ($c) => $c->reject(fn (TicketData $t) => in_array($t->status, $closed, true)))
            ->when($f['situacao'] === 'resolvidos', fn ($c) => $c->filter(fn (TicketData $t) => in_array($t->status, $closed, true)))
            ->when($f['situacao'] === 'atrasados', fn ($c) => $c->filter(fn (TicketData $t) => $t->isOverdue()))
            ->sortByDesc(fn (TicketData $t) => (int) $t->id)
            ->values();
    }

    private function options(Collection $all): array
    {
        $sorted = fn (Collection $c) => $c->unique()->filter()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values();

        return [
            'clientes' => $sorted($all->map(fn (TicketData $t) => $this->company($t))),
            'tecnicos' => $sorted($all->map(fn (TicketData $t) => $t->technicianName ?: 'Não atribuído')),
            'categorias' => $sorted($all->map(fn (TicketData $t) => $t->category ?: 'Sem categoria')),
        ];
    }

    // --------------------------------------------------------------- indicadores

    /** Indicadores de um conjunto de chamados. */
    private function stats(Collection $c): array
    {
        $closed = [TicketStatus::Solved, TicketStatus::Closed];
        $total = $c->count();
        $resolvidos = $c->filter(fn (TicketData $t) => in_array($t->status, $closed, true));
        $comTempo = $resolvidos->filter(fn (TicketData $t) => $t->solvedAt !== null);
        $comSla = $comTempo->filter(fn (TicketData $t) => $t->dueDate !== null);
        $slaOk = $comSla->filter(fn (TicketData $t) => $t->solvedAt->lte($t->dueDate))->count();
        $tempo = $comTempo->isEmpty() ? null : (float) $comTempo->avg(fn (TicketData $t) => max(0, $t->createdAt->diffInMinutes($t->solvedAt)) / 60);

        return [
            'total' => $total,
            'abertos' => $total - $resolvidos->count(),
            'resolvidos' => $resolvidos->count(),
            'atrasados' => $c->filter(fn (TicketData $t) => $t->isOverdue())->count(),
            'resolucao' => $total > 0 ? (int) round($resolvidos->count() / $total * 100) : 0,
            'tempo_h' => $tempo,
            'tempo' => $tempo === null ? '—' : $this->human($tempo),
            'sla' => $comSla->count() > 0 ? (int) round($slaOk / $comSla->count() * 100) : null,
            'sla_base' => $comSla->count(),
        ];
    }

    /** Indicadores agrupados (cliente, técnico, categoria…), do maior volume para o menor. */
    private function groupStats(Collection $list, callable $key): Collection
    {
        return $list->groupBy($key)
            ->map(fn (Collection $g, $nome) => ['nome' => (string) $nome] + $this->stats($g))
            ->sortByDesc('total')
            ->values();
    }

    /** Abertos × resolvidos por dia/semana/mês conforme o tamanho do período. */
    private function series(Collection $list, array $f): array
    {
        $from = $f['periodo'] === 'tudo' && $list->isNotEmpty()
            ? $list->min(fn (TicketData $t) => $t->createdAt)->startOfDay()
            : $f['de'];
        $dias = (int) $from->diffInDays($f['ate']) + 1;
        [$step, $fmt, $label] = $dias <= 31 ? ['day', 'Y-m-d', 'd/m'] : ($dias <= 120 ? ['week', 'o-W', 'd/m'] : ['month', 'Y-m', 'M/y']);

        $buckets = [];
        for ($d = $from->startOf($step); $d->lte($f['ate']) && count($buckets) < 400; $d = $d->add(1, $step)) {
            $buckets[$d->format($fmt)] = ['label' => $d->locale('pt_BR')->translatedFormat($label), 'abertos' => 0, 'resolvidos' => 0];
        }
        foreach ($list as $t) {
            $k = $t->createdAt->startOf($step)->format($fmt);
            if (isset($buckets[$k])) {
                $buckets[$k]['abertos']++;
            }
            if ($t->solvedAt) {
                $k2 = $t->solvedAt->startOf($step)->format($fmt);
                if (isset($buckets[$k2])) {
                    $buckets[$k2]['resolvidos']++;
                }
            }
        }

        return [
            'labels' => array_values(array_column($buckets, 'label')),
            'abertos' => array_values(array_column($buckets, 'abertos')),
            'resolvidos' => array_values(array_column($buckets, 'resolvidos')),
            'unidade' => ['day' => 'por dia', 'week' => 'por semana', 'month' => 'por mês'][$step],
        ];
    }

    // -------------------------------------------------------------------- marcas

    private function brands(GlpiInventoryRepositoryInterface $inventory): array
    {
        // Gestor: escopo raiz recursivo caso a sessão ainda não tenha.
        if (! session()->has('glpi_entity')) {
            session(['glpi_entity' => 0, 'glpi_entity_recursive' => true]);
        }

        try {
            $assets = $inventory->assets();
            $valores = AssetValue::get()->keyBy(fn (AssetValue $v) => $v->itemtype.'-'.$v->item_id);
            $assets = $assets->map(function (array $a) use ($valores) {
                $meta = $valores->get(($a['typeKey'] ?? '').'-'.($a['id'] ?? 0));
                $a['manufacturer'] = ($a['manufacturer'] && $a['manufacturer'] !== '—') ? $a['manufacturer'] : (string) (optional($meta)->marca ?? '—');
                $a['model'] = ($a['model'] && $a['model'] !== '—') ? $a['model'] : (string) (optional($meta)->modelo ?? '—');

                return $a;
            });
        } catch (\Throwable $e) {
            Log::warning('Relatórios: falha ao carregar o inventário: '.$e->getMessage());
            $assets = collect();
        }

        $totalAssets = $assets->count();
        $normalize = function ($m) {
            $v = trim((string) $m);
            if ($v === '' || $v === '0' || $v === '—' || in_array(strtolower($v), ['null', 'genérico'], true)) {
                return 'Outras / Não informada';
            }

            return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        };

        $byMarca = $assets->groupBy(fn (array $a) => $normalize($a['manufacturer'] ?? null))
            ->map(fn (Collection $g, string $marca) => [
                'marca' => $marca,
                'count' => $g->count(),
                'pct' => $totalAssets > 0 ? round($g->count() / $totalAssets * 100, 1) : 0,
                'tipos' => $g->groupBy('type')->map->count()->all(),
                'clientes' => $g->pluck('entity')->unique()->filter()->values()->all(),
                'modelos' => $g->pluck('model')->filter(fn ($m) => $m && $m !== '—')->unique()->take(4)->values()->all(),
            ])
            ->sortByDesc('count')->values();

        $validas = $byMarca->reject(fn (array $m) => $m['marca'] === 'Outras / Não informada')->values();

        return [
            'byMarca' => $byMarca,
            'metricasMarcas' => [
                'total_ativos' => $totalAssets,
                'total_marcas' => $validas->count(),
                'marca_lider' => $validas->first()['marca'] ?? '—',
                'marca_lider_count' => $validas->first()['count'] ?? 0,
                'sem_marca' => $byMarca->firstWhere('marca', 'Outras / Não informada')['count'] ?? 0,
            ],
        ];
    }

    // ------------------------------------------------------------------- apoio

    /** "Drogacei › FL 01" → "Drogacei" (empresa); sem "›" é o próprio nome. */
    private function company(TicketData $t): string
    {
        return trim(Str::before((string) $t->entity, '›')) ?: '—';
    }

    private function branch(TicketData $t): string
    {
        return str_contains((string) $t->entity, '›') ? trim(Str::after((string) $t->entity, '›')) : '';
    }

    private function slaLabel(TicketData $t): string
    {
        if ($t->dueDate === null) {
            return 'Sem prazo';
        }
        if ($t->solvedAt !== null) {
            return $t->solvedAt->lte($t->dueDate) ? 'No prazo' : 'Fora do prazo';
        }

        return $t->isOverdue() ? 'Atrasado' : 'Em andamento';
    }

    private function human(float $hours): string
    {
        if ($hours < 1) {
            return max(1, (int) round($hours * 60)).' min';
        }
        if ($hours < 48) {
            return $this->num($hours).' h';
        }

        return $this->num($hours / 24).' dias';
    }

    private function num(float $n): string
    {
        return number_format($n, 1, ',', '.');
    }

    private function date(mixed $v): ?CarbonImmutable
    {
        try {
            return is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? CarbonImmutable::createFromFormat('Y-m-d', $v) : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
