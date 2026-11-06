<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoveGame;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class GameAttachmentController extends Controller
{
    /**
     * Upload a file (photo, voice message...) to attach to a message of the
     * game chat.
     */
    public function store(Request $request, LoveGame $game): JsonResponse
    {
        Gate::authorize('view', $game);

        return AttachmentController::storeIn($request, "game-media/{$game->id}");
    }
}
