<?php

namespace App\Http\Controllers\Api;

use App\Actions\UpdateGameReceipts;
use App\Http\Controllers\Controller;
use App\Models\LoveGame;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class GameReadController extends Controller
{
    /**
     * The user saw the game chat: the messages sent so far are read.
     */
    public function store(Request $request, LoveGame $game, UpdateGameReceipts $receipts): Response
    {
        Gate::authorize('view', $game);

        $receipts->markRead($game, $request->user());

        return response()->noContent();
    }
}
