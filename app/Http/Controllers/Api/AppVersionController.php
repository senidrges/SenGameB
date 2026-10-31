<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class AppVersionController extends Controller
{
    /**
     * The Android version published on the Play Store, so that older apps
     * offer to update (or must, below the minimum version).
     */
    public function show(): JsonResponse
    {
        return response()->json([
            'android' => [
                'latest_version' => config('services.play_store.latest_version'),
                'min_version' => config('services.play_store.min_version'),
                'store_url' => config('services.play_store.url'),
            ],
        ]);
    }
}
