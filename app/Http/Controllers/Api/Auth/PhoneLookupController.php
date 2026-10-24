<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PhoneLookupController extends Controller
{
    /**
     * Tell the login screen whether a phone number belongs to an account,
     * without exposing that account's profile.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['phone_number' => ['required', 'string', 'min:8', 'max:32']]);

        $exists = User::query()
            ->where('phone_number', PhoneNumber::normalize($request->string('phone_number')))
            ->exists();

        return response()->json(['exists' => $exists]);
    }
}
