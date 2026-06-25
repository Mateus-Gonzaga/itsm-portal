<?php

namespace App\Http\Controllers;

use App\Repositories\Zabbix\ZabbixRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class DashboardsController extends Controller
{
    public function __invoke(Request $request, ZabbixRepositoryInterface $zabbix): View
    {
        try {
            // Deriva os "clientes" dos host groups: "Clientes/<Cliente>/<Tipo>".
            $map = [];
            foreach ($zabbix->groups() as $g) {
                $parts = explode('/', $g['name']);
                if (count($parts) < 2 || $parts[0] !== 'Clientes') {
                    continue;
                }
                $cliente = $parts[1];
                $tipo = mb_strtolower($parts[2] ?? '');
                $map[$cliente] ??= ['all' => [], 'servidores' => [], 'caixas' => []];
                $map[$cliente]['all'][] = $g['groupid'];
                if (str_starts_with($tipo, 'servidor')) {
                    $map[$cliente]['servidores'][] = $g['groupid'];
                } elseif (str_starts_with($tipo, 'caixa')) {
                    $map[$cliente]['caixas'][] = $g['groupid'];
                }
            }
            $clientes = array_keys($map);
            sort($clientes);

            $selected = $request->string('cliente')->value() ?: null;
            if ($selected !== null && ! isset($map[$selected])) {
                $selected = null;
            }

            if ($selected === null) {
                // Visão Geral (todos).
                return view('modules.dashboards', [
                    'mode' => 'geral', 'clientes' => $clientes, 'selected' => null, 'error' => null,
                    'overview' => $zabbix->overview(),
                    'hosts' => $zabbix->hosts(),
                    'problems' => $zabbix->problems(),
                ]);
            }

            $cg = $map[$selected];
            $serv = $zabbix->hosts($cg['servidores']);
            $caixa = $zabbix->hosts($cg['caixas']);
            $prob = $zabbix->problems($cg['all']);

            return view('modules.dashboards', [
                'mode' => 'cliente', 'clientes' => $clientes, 'selected' => $selected, 'error' => null,
                'servidores' => $serv,
                'caixas' => $caixa,
                'problems' => $prob,
                'resumo' => [
                    'servOn' => $serv->where('available', 1)->count(),
                    'servOff' => $serv->where('available', 2)->count(),
                    'caixaOn' => $caixa->where('available', 1)->count(),
                    'caixaOff' => $caixa->where('available', 2)->count(),
                    'alertas' => $prob->count(),
                ],
            ]);
        } catch (RuntimeException $e) {
            return view('modules.dashboards', [
                'mode' => 'geral', 'clientes' => [], 'selected' => null,
                'error' => 'Não foi possível falar com o Zabbix: '.$e->getMessage(),
                'overview' => ['hosts' => 0, 'disponiveis' => 0, 'indisponiveis' => 0, 'problemas' => 0, 'porSeveridade' => []],
                'hosts' => collect(), 'problems' => collect(),
            ]);
        }
    }
}
