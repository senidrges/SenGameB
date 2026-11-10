<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\User;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactMatchController extends Controller
{
    /**
     * Find the users whose phone number is in the device's contacts, and
     * remember them: like on WhatsApp, the user can talk and play with them
     * without an invitation. The list mirrors the phone at each sync; the
     * numbers themselves are never stored.
     */
    public function store(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'phone_numbers' => ['required', 'array', 'max:5000'],
            'phone_numbers.*' => ['string', 'max:40'],
        ]);

        $phoneNumbers = collect($validated['phone_numbers'])
            ->map(fn (string $phoneNumber) => PhoneNumber::normalize($phoneNumber))
            ->filter()
            ->unique()
            ->values();

        $users = $phoneNumbers
            ->chunk(500)
            ->flatMap(fn ($chunk) => User::query()
                ->whereNotNull('username')
                ->whereKeyNot($request->user()->id)
                ->withoutBlocksInvolving($request->user())
                ->whereNull('suspended_at')
                ->whereIn('phone_number', $chunk->all())
                ->get());

        $request->user()->contacts()->sync($users->pluck('id')->unique()->all());

        return ProfileResource::collection($users);
    }
}
