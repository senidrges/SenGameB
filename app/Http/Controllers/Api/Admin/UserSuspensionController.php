<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\SuspendAccount;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Suspend or reinstate an account, restricted to admins by the route middleware.
 */
class UserSuspensionController extends Controller
{
    public function store(Request $request, User $user, SuspendAccount $suspendAccount): Response
    {
        abort_if($user->is_admin, 422, 'Un administrateur ne peut pas être suspendu.');

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:255'],
        ]);

        $suspendAccount->suspend($user, $validated['reason']);

        return response()->noContent();
    }

    public function destroy(User $user, SuspendAccount $suspendAccount): Response
    {
        $suspendAccount->reinstate($user);

        return response()->noContent();
    }
}
