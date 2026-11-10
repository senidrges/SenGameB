<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserSearchResultResource;
use App\Models\User;
use App\Support\ContactRelationship;
use App\Support\PhoneNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Profiles of the other users.
 */
class UserController extends Controller
{
    /**
     * Search users by phone number, or by username, first and last name.
     * Each result says whether the viewer can already talk with the user or
     * must invite them first (see ContactRelationship).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'search' => ['required', 'string', 'min:1', 'max:50'],
        ]);

        $viewer = $request->user();
        $search = trim($validated['search']);
        $phoneNumber = self::searchedPhoneNumber($search);

        $users = User::query()
            ->whereNotNull('username')
            ->whereKeyNot($viewer->id)
            ->withoutBlocksInvolving($viewer)
            ->whereNull('suspended_at')
            ->when(
                $phoneNumber !== null,
                fn (Builder $query) => $query->where('phone_number', $phoneNumber),
                fn (Builder $query) => self::matchingName($query, $search),
            )
            ->orderBy('username')
            ->limit(20)
            ->get();

        $relationships = ContactRelationship::forViewer($viewer, $users);

        return UserSearchResultResource::collection($users->map(fn (User $user) => [
            'user' => $user,
            'relationship' => $relationships[$user->id],
            'show_phone' => $phoneNumber !== null,
        ]));
    }

    /**
     * A blocked user (either way) is reported as not found, like on other social networks.
     */
    public function show(Request $request, User $user): UserSearchResultResource
    {
        $viewer = $request->user();

        abort_unless($user->hasProfile() && ! $user->isSuspended() && ! $viewer->hasBlockWith($user), 404);

        $relationship = $viewer->is($user)
            ? ['status' => ContactRelationship::CONNECTED, 'request_id' => null, 'can_send_intro' => false]
            : ContactRelationship::forViewer($viewer, $user->newCollection([$user]))[$user->id];

        return UserSearchResultResource::make([
            'user' => $user,
            'relationship' => $relationship,
            'show_phone' => false,
        ]);
    }

    /**
     * The search is a phone number when it is made of digits (spaces, "+",
     * dashes and dots allowed) long enough for a national number.
     */
    private static function searchedPhoneNumber(string $search): ?string
    {
        if (preg_match('/^[\d\s+\-.()]+$/', $search) !== 1) {
            return null;
        }

        $digits = preg_replace('/\D/', '', $search) ?? '';

        return strlen($digits) >= PhoneNumber::NATIONAL_LENGTH ? PhoneNumber::normalize($search) : null;
    }

    /**
     * Every word must be found in the username, the first or the last name:
     * "awa ouedraogo" finds Awa Ouédraogo whatever the order.
     */
    private static function matchingName(Builder $query, string $search): void
    {
        $words = collect(preg_split('/\s+/', $search) ?: [])->filter()->take(4);

        foreach ($words as $word) {
            $pattern = '%'.addcslashes($word, '\\%_').'%';

            $query->where(fn (Builder $name) => $name
                ->whereLike('username', $pattern)
                ->orWhereLike('first_name', $pattern)
                ->orWhereLike('last_name', $pattern));
        }
    }
}
