<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuthSessionResource;
use App\Services\AuthTokens;
use Illuminate\Http\Request;

class RefreshTokenController extends Controller
{
    /**
     * Exchange a refresh token for a new token pair. The old pair is revoked.
     */
    public function store(Request $request, AuthTokens $tokens): AuthSessionResource
    {
        $user = $request->user();

        return AuthSessionResource::make($user, $tokens->rotate($user->currentAccessToken()));
    }
}
