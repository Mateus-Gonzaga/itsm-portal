<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Janela de atendimento: horários em que o suporte funciona + feriados.
 * É a base para o cálculo de SLA. Persistido em cache (mock); na Fase 2
 * pode mapear para a configuração de calendário/SLA do GLPI.
 */
class ServiceWindowController extends Controller
{
    private const CACHE_KEY = 'service_window';

    /** @var array<string, string> */
    private const DAYS = [
        'mon' => 'Segunda-feira',
        'tue' => 'Terça-feira',
        'wed' => 'Quarta-feira',
        'thu' => 'Quinta-feira',
        'fri' => 'Sexta-feira',
        'sat' => 'Sábado',
        'sun' => 'Domingo',
    ];

    public function index(): View
    {
        return view('modules.schedule', [
            'days' => self::DAYS,
            'window' => $this->current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'days' => ['nullable', 'array'],
            'start' => ['nullable', 'array'],
            'end' => ['nullable', 'array'],
            'holidays' => ['nullable', 'string', 'max:4000'],
        ]);

        $window = ['days' => [], 'holidays' => trim((string) ($data['holidays'] ?? ''))];
        foreach (array_keys(self::DAYS) as $key) {
            $window['days'][$key] = [
                'enabled' => isset($data['days'][$key]),
                'start' => $data['start'][$key] ?? '08:00',
                'end' => $data['end'][$key] ?? '18:00',
            ];
        }

        cache()->forever(self::CACHE_KEY, $window);

        return back()->with('status', 'Janela de atendimento atualizada.');
    }

    /** @return array{days: array<string, array{enabled: bool, start: string, end: string}>, holidays: string} */
    private function current(): array
    {
        return cache()->get(self::CACHE_KEY, $this->defaults());
    }

    private function defaults(): array
    {
        $days = [];
        foreach (array_keys(self::DAYS) as $key) {
            $isWeekday = ! in_array($key, ['sat', 'sun'], true);
            $days[$key] = ['enabled' => $isWeekday, 'start' => '08:00', 'end' => '18:00'];
        }

        return [
            'days' => $days,
            'holidays' => "01/01 — Confraternização Universal\n21/04 — Tiradentes\n01/05 — Dia do Trabalho\n25/12 — Natal",
        ];
    }
}
