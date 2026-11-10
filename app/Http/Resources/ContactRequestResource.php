<?php

namespace App\Http\Resources;

use App\Models\ContactRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An invitation seen by one of its two users: `user` is the other one.
 * Their phone number is not shown, the invitation is for people who do
 * not have it.
 *
 * @mixin ContactRequest
 */
class ContactRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSender = $request->user()?->id === $this->sender_id;
        $other = $isSender ? $this->recipient : $this->sender;

        return [
            'id' => $this->id,
            'direction' => $isSender ? 'sent' : 'received',
            'status' => $this->status->value,
            'message' => $this->message,
            'user' => $other === null ? null : [
                ...ProfileResource::make($other)->resolve($request),
                'phone_number' => null,
            ],
            'responded_at' => $this->responded_at,
            'created_at' => $this->created_at,
        ];
    }
}
