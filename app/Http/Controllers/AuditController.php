<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Trilha de auditoria (somente leitura) — gestor. */
class AuditController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->string('q'));

        $logs = AuditLog::query()
            ->when($q !== '', fn ($qb) => $qb->where(fn ($w) => $w
                ->where('description', 'like', "%{$q}%")
                ->orWhere('user_name', 'like', "%{$q}%")
                ->orWhere('action', 'like', "%{$q}%")))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('modules.audit', ['logs' => $logs, 'q' => $q]);
    }
}
