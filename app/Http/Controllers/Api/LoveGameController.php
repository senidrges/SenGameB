<?php

namespace App\Http\Controllers\Api;

use App\Actions\CreateLoveGame;
use App\Events\GameChanged;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLoveGameRequest;
use App\Http\Resources\LoveGameResource;
use App\Models\LoveGame;
use App\Support\Realtime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LoveGameController extends Controller
{
    /**
     * Games the user organizes or plays, most recent activity first.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $games = LoveGame::query()
            ->withParticipant($request->user())
            ->with(LoveGame::DETAIL_RELATIONS)
            ->orderByDesc('updated_at')
            ->get();

        return LoveGameResource::collection($games);
    }

    public function store(StoreLoveGameRequest $request, CreateLoveGame $createLoveGame): JsonResponse
    {
        $game = $createLoveGame->handle(
            organizer: $request->user(),
            title: $request->validated('title'),
            playerIds: $request->validated('player_ids'),
            items: $request->validated('items'),
            conversationId: $request->validated('conversation_id'),
        );

        return LoveGameResource::make($game)->response()->setStatusCode(201);
    }

    public function show(LoveGame $game): LoveGameResource
    {
        Gate::authorize('view', $game);

        return LoveGameResource::make($game->load(LoveGame::DETAIL_RELATIONS));
    }

    public function destroy(LoveGame $game): Response
    {
        Gate::authorize('delete', $game);

        $participantIds = $game->participantIds();
        $game->delete();

        Realtime::send(GameChanged::deleted($game, $participantIds));

        return response()->noContent();
    }
}
