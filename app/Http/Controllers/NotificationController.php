<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {
    }

    /** Payload do sino (contagem + itens). Consumido por AJAX/poll. */
    public function index(Request $request): JsonResponse
    {
        return response()->json($this->notifications->for($request->user()));
    }

    /** Marca tudo como lido (zera o sino). */
    public function markRead(Request $request): JsonResponse
    {
        $this->notifications->markRead($request->user());

        return response()->json(['ok' => true]);
    }
}
