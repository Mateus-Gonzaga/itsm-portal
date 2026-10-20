<?php

namespace App\Http\Controllers;

use App\Data\TicketData;
use App\Repositories\Glpi\GlpiDirectoryRepositoryInterface;
use App\Repositories\Glpi\GlpiInventoryRepositoryInterface;
use App\Repositories\Glpi\GlpiTicketRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Busca global (Ctrl+K) da equipe: chamados, clientes, equipamentos e telas.
 * Lê das mesmas listas em cache do GLPI usadas pelas telas (sem consultas extras).
 */
class SearchController extends Controller
{
    private const LIMITE = 6;

    public function __invoke(
        Request $request,
        GlpiTicketRepositoryInterface $tickets,
        GlpiDirectoryRepositoryInterface $directory,
        GlpiInventoryRepositoryInterface $inventory,
    ): JsonResponse {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2 && ! ctype_digit($q)) {
            return response()->json(['grupos' => []]);
        }

        $termo = Str::ascii(mb_strtolower($q));
        $casa = fn (...$campos) => collect($campos)->contains(
            fn ($c) => $c !== null && str_contains(Str::ascii(mb_strtolower((string) $c)), $termo)
        );
        $limpa = fn ($s) => html_entity_decode((string) $s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $grupos = [];

        // Telas do menu do usuário (atalho de navegação).
        $telas = collect($request->user()->role->menu())
            ->filter(fn (array $m) => $casa($m['label']))
            ->map(fn (array $m) => ['titulo' => $m['label'], 'detalhe' => 'Ir para a tela', 'icone' => $m['icon'], 'url' => route($m['route'])])
            ->take(4)->values()->all();
        if ($telas) {
            $grupos[] = ['nome' => 'Telas', 'itens' => $telas];
        }

        // Chamados: nº exato primeiro, depois título/cliente/solicitante (mais recentes antes).
        try {
            $numero = ltrim($q, '#');
            $lista = $tickets->all()
                ->filter(fn (TicketData $t) => (string) $t->id === $numero || $casa($t->title, $t->entity, $t->requesterName))
                ->sortByDesc(fn (TicketData $t) => ((string) $t->id === $numero ? 10_000_000_000 : 0) + $t->createdAt->getTimestamp())
                ->take(self::LIMITE)
                ->map(fn (TicketData $t) => [
                    'titulo' => '#'.$t->id.' · '.$limpa($t->title),
                    'detalhe' => trim($t->status->label().' · '.$limpa(Arr::last(preg_split('/\s*[>›]\s*/u', (string) $t->entity) ?: [''])), ' ·'),
                    'icone' => $t->type->icon(),
                    'url' => route('tickets.show', $t->id),
                ])->values()->all();
            if ($lista) {
                $grupos[] = ['nome' => 'Chamados', 'itens' => $lista];
            }
        } catch (\Throwable) {
            // GLPI fora: a busca segue com o que houver.
        }

        // Clientes (entidades): abre o perfil do cliente.
        try {
            $clientes = $directory->entities()
                ->filter(fn (array $e) => $e['level'] > 1 && $casa($e['name']))
                ->take(self::LIMITE)
                ->map(fn (array $e) => [
                    'titulo' => $limpa($e['name']),
                    'detalhe' => $limpa($e['completename']),
                    'icone' => 'bi-building',
                    'url' => route('clients.profile.show', $e['id']),
                ])->values()->all();
            if ($clientes) {
                $grupos[] = ['nome' => 'Clientes', 'itens' => $clientes];
            }
        } catch (\Throwable) {
        }

        // Equipamentos: nome, série, modelo ou fabricante → inventário filtrado.
        try {
            $ativos = $inventory->assets()
                ->filter(fn (array $a) => $casa($a['name'] ?? null, $a['serial'] ?? null, $a['model'] ?? null))
                ->take(self::LIMITE)
                ->map(fn (array $a) => [
                    'titulo' => $limpa($a['name'] ?? ''),
                    'detalhe' => trim(($a['type'] ?? '').' · '.$limpa($a['entity'] ?? ''), ' ·'),
                    'icone' => $a['icon'] ?? 'bi-pc-display',
                    'url' => route('modules.inventory', ['q' => $a['name'] ?? '']),
                ])->values()->all();
            if ($ativos) {
                $grupos[] = ['nome' => 'Equipamentos', 'itens' => $ativos];
            }
        } catch (\Throwable) {
        }

        return response()->json(['grupos' => $grupos]);
    }
}
