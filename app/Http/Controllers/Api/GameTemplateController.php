<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\GameTemplateResource;
use App\Models\GameTemplate;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class GameTemplateController extends Controller
{
    /**
     * Templates published by the admins.
     */
    public function index(): AnonymousResourceCollection
    {
        return GameTemplateResource::collection(GameTemplate::query()->published()->catalogOrder()->get());
    }
}
