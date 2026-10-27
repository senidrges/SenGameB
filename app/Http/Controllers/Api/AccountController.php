<?php

namespace App\Http\Controllers\Api;

use App\Actions\DeleteAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AccountController extends Controller
{
    /**
     * Delete the user's account for good, after confirming the password.
     */
    public function destroy(Request $request, DeleteAccount $deleteAccount): Response
    {
        $validated = $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();

        if (! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Mot de passe incorrect.']);
        }

        $deleteAccount->delete($user);

        return response()->noContent();
    }
}
