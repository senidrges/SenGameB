<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\ContactRelationship;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A profile found by the search, with where the viewer stands with it
 * (`relationship`, see ContactRelationship). The phone number stays hidden
 * from strangers unless it is the number that was searched.
 *
 * @property array{user: User, relationship: array{status: string, request_id: string|null, can_send_intro: bool}, show_phone: bool} $resource
 */
class UserSearchResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        ['user' => $user, 'relationship' => $relationship, 'show_phone' => $showPhone] = $this->resource;

        return [
            ...ProfileResource::make($user)->resolve($request),
            'phone_number' => $relationship['status'] === ContactRelationship::CONNECTED || $showPhone ? $user->phone_number : null,
            'relationship' => $relationship,
        ];
    }
}
