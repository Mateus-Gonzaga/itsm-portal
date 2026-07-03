<?php

namespace App\Http\Controllers;

use App\Models\KanbanCard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Quadro Kanban da equipe (colunas A fazer / Em andamento / Concluído). */
class KanbanController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateCard($request);

        KanbanCard::create([
            ...$data,
            'status' => $data['status'] ?? 'todo',
            'position' => (int) KanbanCard::where('status', $data['status'] ?? 'todo')->max('position') + 1,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Cartão criado.');
    }

    public function update(Request $request, KanbanCard $card): RedirectResponse
    {
        $card->update($this->validateCard($request));

        return back()->with('status', 'Cartão atualizado.');
    }

    /** Move/reordena via drag-and-drop (AJAX). Recebe a coluna e a ordem dos ids. */
    public function move(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(KanbanCard::STATUSES)],
            'order' => ['array'],
            'order.*' => ['integer'],
        ]);

        foreach ($data['order'] as $i => $id) {
            KanbanCard::where('id', (int) $id)->update([
                'status' => $data['status'],
                'position' => $i,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function destroy(KanbanCard $card): RedirectResponse
    {
        $card->delete();

        return back()->with('status', 'Cartão excluído.');
    }

    /** @return array<string, mixed> */
    private function validateCard(Request $request): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', Rule::in(KanbanCard::STATUSES)],
            'assignee_glpi_id' => ['nullable', 'integer'],
            'assignee_name' => ['nullable', 'string', 'max:150'],
            'due_date' => ['nullable', 'date'],
            'color' => ['nullable', 'string', 'max:20'],
        ]);

        // Sem responsável selecionado: limpa nome também.
        if (empty($data['assignee_glpi_id'])) {
            $data['assignee_glpi_id'] = null;
            $data['assignee_name'] = null;
        }

        return $data;
    }
}
