<?php

namespace App\Http\Resources;

use App\Actions\GroupCalls;
use App\Enums\CallParticipantStatus;
use App\Enums\GroupRole;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

/**
 * A conversation seen by the current user, shaped like `ConversationWithDetails`.
 *
 * Load the participants with Conversation::participantsWithBlockStateFor()
 * to avoid one block query per conversation.
 *
 * A one-to-one conversation carries the other `participant`; a group
 * carries `group` (name, photo, permissions, the viewer's role) and, with
 * withMembers(), the list of its members.
 *
 * @mixin Conversation
 */
class ConversationResource extends JsonResource
{
    private bool $withMembers = false;

    /**
     * Add the members of a group (info screen, chat header).
     */
    public function withMembers(): static
    {
        $this->withMembers = true;

        return $this;
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user();
        $other = $this->relationLoaded('participants') ? $this->otherParticipant($viewer) : null;
        $isGroup = $this->isGroup();

        return [
            'id' => $this->id,
            'type' => $this->type,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'participant' => $this->whenLoaded('participants', fn () => $isGroup ? null : ProfileResource::make($other)),
            'group' => $this->when($isGroup, fn () => $this->groupDetails($viewer)),
            'last_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage === null
                ? null
                : MessageResource::make($this->latestMessage)),
            'unread_count' => (int) ($this->unread_count ?? 0),
            // Unread messages naming the user with @ (the "@" of the list)
            'unread_mentions_count' => (int) ($this->unread_mentions_count ?? 0),
            // Disappearing messages: seconds before new messages go (null: off)
            'disappearing_seconds' => $this->disappearing_seconds,
            'blocked_by_me' => $this->whenLoaded('participants', fn () => ! $isGroup && $other !== null && $this->viewerBlocked($viewer, $other)),
            'can_message' => $this->whenLoaded('participants', fn () => $isGroup
                ? Gate::forUser($viewer)->allows('sendMessage', $this->resource)
                : $other !== null && ! $this->isBlockedEitherWay($viewer, $other)),
            'is_archived' => $this->whenLoaded('participants', fn () => $this->viewerPivot($viewer)?->archived_at !== null),
            'is_locked' => $this->whenLoaded('participants', fn () => $this->viewerPivot($viewer)?->locked_at !== null),
            'is_muted' => $this->whenLoaded('participants', fn () => ($pivot = $this->viewerPivot($viewer)) !== null && Conversation::pivotIsMuted($pivot)),
            'muted_until' => $this->whenLoaded('participants', fn () => $this->viewerPivot($viewer)?->muted_until === null
                ? null
                : Carbon::parse($this->viewerPivot($viewer)->muted_until)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function groupDetails(User $viewer): array
    {
        $pivot = $this->viewerPivot($viewer);
        $isMember = $pivot !== null && $pivot->left_at === null;
        $isAdmin = $isMember && $pivot->role === GroupRole::Admin->value;
        $members = $this->relationLoaded('participants')
            ? $this->participants->filter(fn (User $participant) => $participant->pivot->left_at === null)
            : collect();

        return [
            'name' => $this->name,
            'description' => $this->description,
            'avatar_url' => $this->avatar_url,
            'avatar_thumb_url' => $this->avatar_thumb_url,
            'created_by' => $this->created_by,
            'member_count' => $members->count(),
            'role' => $isMember ? $pivot->role : null,
            'is_member' => $isMember,
            'left_at' => $pivot?->left_at === null ? null : Carbon::parse($pivot->left_at),
            'can_edit_info' => $this->canEditInfo($viewer),
            'can_add_members' => $this->canAddMembers($viewer),
            'settings' => [
                'members_can_edit_info' => $this->members_can_edit_info,
                'members_can_send' => $this->members_can_send,
                'members_can_add' => $this->members_can_add,
                'approval_required' => $this->approval_required,
            ],
            // Group call going on: "Rejoindre" in the chat, like WhatsApp
            'active_call' => $this->activeCall($viewer),
            'pending_requests_count' => $isAdmin && $this->withMembers ? $this->joinRequests()->count() : 0,
            'members' => $this->when($this->withMembers, fn () => $members
                // Like WhatsApp: you first, then the admins, then by name
                ->sortBy(fn (User $member) => [
                    $member->is($viewer) ? 0 : 1,
                    $member->pivot->role === GroupRole::Admin->value ? 0 : 1,
                    mb_strtolower((string) $member->username),
                ])
                ->values()
                ->map(fn (User $member) => [
                    'user' => ProfileResource::make($member),
                    'role' => $member->pivot->role,
                    'is_creator' => $member->id === $this->created_by,
                    'joined_at' => $member->pivot->joined_at === null ? null : Carbon::parse($member->pivot->joined_at),
                ])),
        ];
    }

    /**
     * @return array{id: string, type: string, participant_count: int, has_joined: bool}|null
     */
    private function activeCall(User $viewer): ?array
    {
        $call = app(GroupCalls::class)->ongoingIn($this->resource);

        if ($call === null) {
            return null;
        }

        $joined = $call->participants()->wherePivot('status', CallParticipantStatus::Joined->value)->pluck('users.id');

        return [
            'id' => $call->id,
            'type' => $call->type->value,
            'participant_count' => $joined->count(),
            'has_joined' => $joined->contains($viewer->id),
        ];
    }

    /**
     * Archive, lock and mute are set per participant, on the viewer's own pivot row.
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
