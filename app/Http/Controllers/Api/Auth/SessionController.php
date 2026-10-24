<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\AuthSessionResource;
use App\Models\User;
use App\Services\AuthTokens;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SessionController extends Controller
{
    /**
     * Log in with email and password.
     */
    public function store(LoginRequest $request, AuthTokens $tokens): AuthSessionResource
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Email ou mot de passe incorrect.',
            ]);
        }

        if ($user->isSuspended()) {
            throw new HttpResponseException(EnsureAccountIsActive::suspendedResponse());
        }

        if (Hash::needsRehash($user->password)) {
            $user->update(['password' => $request->validated('password')]);
        }

        return AuthSessionResource::make($user, $tokens->issue($user));
    }

    /**
     * Log out of the current device.
     */
    public function destroy(Request $request, AuthTokens $tokens): Response
    {
        $user = $request->user();

        $user->update(['is_online' => false, 'last_seen_at' => now()]);
        $tokens->revokeSession($user->currentAccessToken());

        return response()->noContent();
    }
}
