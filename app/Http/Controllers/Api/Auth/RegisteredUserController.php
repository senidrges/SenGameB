<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\AuthSessionResource;
use App\Models\User;
use App\Services\AuthTokens;
use Illuminate\Http\JsonResponse;

class RegisteredUserController extends Controller
{
    /**
     * Create an account. The public profile is completed during onboarding.
     */
    public function store(RegisterRequest $request, AuthTokens $tokens): JsonResponse
    {
        $user = User::create($request->validated());

        return AuthSessionResource::make($user, $tokens->issue($user))
            ->response()
            ->setStatusCode(201);
    }
}
