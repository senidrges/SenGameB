<?php

namespace App\Http\Controllers\Api;

use App\Actions\ManageGroup;
use App\Enums\GroupRole;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\ProfileResource;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Members of a group: adding, removing, naming admins and leaving, with
 * the rules of WhatsApp.
 */
class GroupMemberController extends Controller
{
    /**
     * People the user can put in a group: those they can talk to (their
     * contacts, accepted invitations, their conversations), blocks aside.
     */
    public function candidates(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $ids = DB::table('user_contacts')->where('owner_id', $user->id)->pluck('contact_id')
            ->merge(DB::table('user_contacts')->where('contact_id', $user->id)->pluck('owner_id'))
            ->merge(DB::table('contact_requests')->where('status', 'accepted')->where('sender_id', $user->id)->pluck('recipient_id'))
            ->merge(DB::table('contact_requests')->where('status', 'accepted')->where('recipient_id', $user->id)->pluck('sender_id'))
            ->merge(DB::table('conversation_participants as mine')
                ->join('conversations', 'conversations.id', '=', 'mine.conversation_id')
                ->join('conversation_participants as theirs', 'theirs.conversation_id', '=', 'mine.conversation_id')
                ->where('conversations.type', 'direct')
                ->where('mine.user_id', $user->id)
                ->where('theirs.user_id', '!=', $user->id)
                ->pluck('theirs.user_id'))
            ->unique()
            ->reject(fn (string $id) => $id === $user->id)
            ->values()
            ->all();

        $users = User::query()
            ->whereKey($ids)
            ->whereNotNull('username')
            ->whereNull('suspended_at')
            ->withoutBlocksInvolving($user)
            ->orderBy('username')
            ->get();

        return ProfileResource::collection($users);
    }

    /**
     * Add people the user can reach. Admins always can; members when
     * "Ajouter d'autres membres" is open to everyone.
     */
    public function store(Request $request, Conversation $conversation, ManageGroup $manageGroup): ConversationResource
    {
        $this->ensureGroup($conversation);
        Gate::authorize('addMembers', $conversation);

        $user = $request->user();

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1', 'max:'.Conversation::MAX_GROUP_MEMBERS],
            'user_ids.*' => ['required', 'uuid', 'distinct'],
        ]);

        $currentIds = $conversation->members()->pluck('users.id')->all();
        $newIds = array_values(array_diff($validated['user_ids'], $currentIds, [$user->id]));

        abort_if(
            count($currentIds) + count($newIds) > Conversation::MAX_GROUP_MEMBERS,
            422,
            'Un groupe compte au plus '.Conversation::MAX_GROUP_MEMBERS.' membres.',
        );

        $users = GroupController::reachableUsers($user, $newIds, 'user_ids');
        $manageGroup->addMembers($conversation, $user, $users);

        return $this->show($request, $conversation);
    }

    /**
     * An admin removes a member. Like WhatsApp, the creator of the group
     * cannot be removed by the other admins.
     */
    public function destroy(Request $request, Conversation $conversation, User $user, ManageGroup $manageGroup): ConversationResource
    {
        $this->ensureGroup($conversation);
        Gate::authorize('administer', $conversation);

        $actor = $request->user();
        abort_if($actor->is($user), 422, 'Utilisez « Quitter le groupe ».');
        abort_unless($conversation->hasMember($user), 404);
        abort_if(
            $user->id === $conversation->created_by && $actor->id !== $conversation->created_by,
            403,
            'Le créateur du groupe ne peut pas être retiré.',
        );

        $manageGroup->removeMember($conversation, $actor, $user);

        return $this->show($request, $conversation);
    }

    /**
     * "Nommer admin du groupe" / "Retirer des admins".
     */
    public function updateRole(Request $request, Conversation $conversation, User $user, ManageGroup $manageGroup): ConversationResource
    {
        $this->ensureGroup($conversation);
        Gate::authorize('administer', $conversation);

        $validated = $request->validate([
            'role' => ['required', Rule::enum(GroupRole::class)],
        ]);

        $actor = $request->user();
        $role = GroupRole::from($validated['role']);

        abort_unless($conversation->hasMember($user), 404);
        abort_if($actor->is($user) && $role === GroupRole::Member && ! $this->hasOtherAdmin($conversation, $actor), 422, 'Le groupe doit garder au moins un admin.');
        abort_if(
            $role === GroupRole::Member && $user->id === $conversation->created_by && $actor->id !== $conversation->created_by,
            403,
            'Le créateur du groupe reste admin.',
        );

        if ($conversation->pivotOf($user)->role !== $role->value) {
            $manageGroup->setRole($conversation, $actor, $user, $role);
        }

        return $this->show($request, $conversation);
    }

    /**
     * "Quitter le groupe": the history up to now stays readable.
     */
    public function leave(Request $request, Conversation $conversation, ManageGroup $manageGroup): ConversationResource
    {
        $this->ensureGroup($conversation);
        abort_unless($conversation->hasMember($request->user()), 422, 'Vous ne faites plus partie de ce groupe.');

        $manageGroup->leave($conversation, $request->user());

        return $this->show($request, $conversation);
    }

    private function hasOtherAdmin(Conversation $conversation, User $actor): bool
    {
        return $conversation->members()
            ->whereKeyNot($actor->id)
            ->wherePivot('role', GroupRole::Admin->value)
            ->exists();
    }

    private function show(Request $request, Conversation $conversation): ConversationResource
    {
        $conversation->unsetRelation('participants');
        $conversation->load(Conversation::participantsWithBlockStateFor($request->user()));
        Conversation::loadLatestMessageVisibleTo($conversation->newCollection([$conversation]), $request->user());

        return ConversationResource::make($conversation)->withMembers();
    }

    private function ensureGroup(Conversation $conversation): void
    {
        abort_unless($conversation->isGroup(), 404);
    }
}
