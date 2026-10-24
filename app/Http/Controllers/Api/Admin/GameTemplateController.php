<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\GameItemRules;
use App\Http\Resources\GameTemplateResource;
use App\Models\GameTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

/**
 * Template management, restricted to admins by the route middleware.
 */
class GameTemplateController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return GameTemplateResource::collection(GameTemplate::query()->catalogOrder()->get());
    }

    public function store(Request $request): JsonResponse
    {
        $template = GameTemplate::create($request->validate($this->rules('required')));

        return GameTemplateResource::make($template)->response()->setStatusCode(201);
    }

    public function update(Request $request, GameTemplate $gameTemplate): GameTemplateResource
    {
        $gameTemplate->update($request->validate($this->rules('sometimes')));

        return GameTemplateResource::make($gameTemplate);
    }

    public function destroy(GameTemplate $gameTemplate): Response
    {
        $gameTemplate->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(string $presence): array
    {
        return [
            'title' => [$presence, 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'category' => ['sometimes', 'string', 'max:50'],
            'is_published' => ['sometimes', 'boolean'],
            ...GameItemRules::rules($presence),
        ];
    }
}
