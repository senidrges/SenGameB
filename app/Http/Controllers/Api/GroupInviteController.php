<?php

namespace App\Http\Controllers\Api;

use App\Actions\ManageGroup;
use App\Enums\GroupEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\ProfileResource;
use App\Models\Conversation;
use App\Models\GroupJoinRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * "Inviter via un lien", like WhatsApp: admins share a link, anyone who
 * opens it joins the group, or asks to when admins approve new members.
 */
class GroupInviteController extends Controller
{
    /**
     * The link of the group (admins only).
     */
    public function show(Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isGroup(), 404);
        Gate::authorize('administer', $conversation);

        return response()->json($this->link($conversation->ensureInviteCode()));
    }

    /**
     * "Réinitialiser le lien": the previous one stops working.
     */
    public function reset(Request $request, Conversation $conversation, ManageGroup $manageGroup): JsonResponse
    {
        abort_unless($conversation->isGroup(), 404);
        Gate::authorize('administer', $conversation);

        $conversation->resetInviteCode();
        $manageGroup->notice($conversation, $request->user(), GroupEvent::LinkReset);

        return response()->json($this->link($conversation->invite_code));
    }

    /**
     * What the link shows before joining: name, photo, description, size.
     */
    public function preview(Request $request, string $code): JsonResponse
    {
        $group = $this->groupFor($code);
        $user = $request->user();

        return response()->json([
            'id' => $group->id,
            'name' => $group->name,
            'description' => $group->description,
            'avatar_url' => $group->avatar_url,
            'avatar_thumb_url' => $group->avatar_thumb_url,
            'member_count' => $group->members()->count(),
            'is_member' => $group->hasMember($user),
            'approval_required' => $group->approval_required,
            'has_requested' => $group->joinRequests()->where('user_id', $user->id)->exists(),
        ]);
    }

    /**
     * Join through the link, or ask the admins when they approve new members.
     */
    public function join(Request $request, string $code, ManageGroup $manageGroup): JsonResponse
    {
        $group = $this->groupFor($code);
        $user = $request->user();

        if (! $group->hasMember($user)) {
            abort_if(
                $group->members()->count() >= Conversation::MAX_GROUP_MEMBERS,
                422,
                'Ce groupe est complet.',
            );

            if ($group->approval_required) {
                $group->joinRequests()->firstOrCreate(['user_id' => $user->id]);
                $manageGroup->changed($group);

                return response()->json(['status' => 'requested', 'conversation_id' => $group->id], 202);
            }

            $manageGroup->addMembers($group, $user, collect([$user]), GroupEvent::JoinedViaLink);
        }

        return response()->json(['status' => 'joined', 'conversation_id' => $group->id]);
    }

    /**
     * The user cancels their request to join.
     */
    public function cancelRequest(Request $request, string $code, ManageGroup $manageGroup): Response
    {
        $group = $this->groupFor($code);

        if ($group->joinRequests()->where('user_id', $request->user()->id)->delete() > 0) {
            $manageGroup->changed($group);
        }

        return response()->noContent();
    }

    /**
     * Requests waiting for an admin.
     */
    public function requests(Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->isGroup(), 404);
        Gate::authorize('administer', $conversation);

        $requests = $conversation->joinRequests()->with('user')->oldest()->get()
            ->filter(fn (GroupJoinRequest $joinRequest) => $joinRequest->user !== null)
            ->map(fn (GroupJoinRequest $joinRequest) => [
                'id' => $joinRequest->id,
                'user' => ProfileResource::make($joinRequest->user),
                'created_at' => $joinRequest->created_at,
            ])
            ->values();

        return response()->json($requests);
    }

    public function approve(Request $request, Conversation $conversation, GroupJoinRequest $joinRequest, ManageGroup $manageGroup): ConversationResource
    {
        $this->authorizeRequest($conversation, $joinRequest);

        $manageGroup->addMembers($conversation, $joinRequest->user, collect([$joinRequest->user]), GroupEvent::JoinedViaLink);

        $conversation->unsetRelation('participants');
        $conversation->load(Conversation::participantsWithBlockStateFor($request->user()));

        return ConversationResource::make($conversation)->withMembers();
    }

    public function reject(Conversation $conversation, GroupJoinRequest $joinRequest, ManageGroup $manageGroup): Response
    {
        $this->authorizeRequest($conversation, $joinRequest);

        $joinRequest->delete();
        $manageGroup->changed($conversation);

        return response()->noContent();
    }

    private function authorizeRequest(Conversation $conversation, GroupJoinRequest $joinRequest): void
    {
        abort_unless($conversation->isGroup() && $joinRequest->conversation_id === $conversation->id, 404);
        Gate::authorize('administer', $conversation);
    }

    private function groupFor(string $code): Conversation
    {
        return Conversation::query()->where('invite_code', $code)->where('type', 'group')->firstOrFail();
    }

    /**
     * @return array{code: string, url: string}
     */
    private function link(string $code): array
    {
        return ['code' => $code, 'url' => url("/g/{$code}")];
    }
}
