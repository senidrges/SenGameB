<?php

namespace App\Http\Controllers\Api;

use App\Actions\RecordGameChoice;
use App\Http\Controllers\Controller;
use App\Http\Resources\LoveGameResource;
use App\Models\LoveGame;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class GameChoiceController extends Controller
{
    /**
     * The player picks one of the game's numbers.
     */
    public function store(Request $request, LoveGame $game, RecordGameChoice $recordGameChoice): LoveGameResource
    {
        Gate::authorize('choose', $game);

        $validated = $request->validate([
            'choice_number' => [
                'required',
                'integer',
                Rule::exists('game_items', 'number')->where('game_id', $game->id),
            ],
        ]);

        $game = $recordGameChoice->handle($game, $request->user(), $validated['choice_number']);

        return LoveGameResource::make($game);
    }
}
