<?php

namespace App\Http\Resources;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A conversation seen by the current user, shaped like `ConversationWithDetails`.
 *
 * Load the participants with Conversation::participantsWithBlockStateFor()
 * to avoid one block query per conversation.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $other = $this->relationLoaded('participants') ? $this->otherParticipant($viewer) : null;

        return [
            'id' => $this->id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'participant' => $this->whenLoaded('participants', fn () => ProfileResource::make($other)),
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage === null
                ? null
                : MessageResource::make($this->latestMessage)),
            'unread_count' => (int) ($this->unread_count ?? 0),
            'blocked_by_me' => $this->whenLoaded('participants', fn () => $other !== null && $this->viewerBlocked($viewer, $other)),
            'can_message' => $this->whenLoaded('participants', fn () => $other !== null && ! $this->isBlockedEitherWay($viewer, $other)),
            'is_archived' => $this->whenLoaded('participants', fn () => $this->viewerPivot($viewer)?->archived_at !== null),
            'is_locked' => $this->whenLoaded('participants', fn () => $this->viewerPivot($viewer)?->locked_at !== null),
        ];
    }

    /**
     * Archive and lock are set per participant, on the viewer's own pivot row.
     */
    private function viewerPivot(User $viewer): ?object
    {
        return $this->participants->first(fn (User $participant) => $participant->is($viewer))?->pivot;
    }

    private function viewerBlocked(User $viewer, User $other): bool
    {
        return $other->hasAttribute('is_blocked_by_viewer')
            ? (bool) $other->is_blocked_by_viewer
            : $viewer->hasBlocked($other);
    }

    private function isBlockedEitherWay(User $viewer, User $other): bool
    {
        return $other->hasAttribute('is_blocked_by_viewer') && $other->hasAttribute('has_blocked_viewer')
            ? (bool) $other->is_blocked_by_viewer || (bool) $other->has_blocked_viewer
            : $viewer->hasBlockWith($other);
    }
}
