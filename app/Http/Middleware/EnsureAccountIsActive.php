<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Refuse every request of a suspended account, even with a token issued
 * before the suspension.
 */
class EnsureAccountIsActive
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isSuspended()) {
            return self::suspendedResponse();
        }

        return $next($request);
    }

    /**
     * The app recognizes the `account_suspended` code and logs the user out.
     */
    public static function suspendedResponse(): JsonResponse
    {
        return response()->json([
            'message' => 'Votre compte a été suspendu pour non-respect des règles de la communauté.',
            'code' => 'account_suspended',
        ], 403);
    }
}
