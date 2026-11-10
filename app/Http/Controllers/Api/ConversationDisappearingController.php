<?php

namespace App\Http\Controllers\Api;

use App\Actions\ManageGroup;
use App\Enums\GroupEvent;
use App\Http\Controllers\Controller;
use App\Http\Resources\ConversationResource;
use App\Models\Conversation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * "Messages éphémères", like WhatsApp: new messages disappear 24 hours,
 * 7 days or 90 days after they are sent. In a one-to-one chat either side
 * sets it; in a group, whoever may edit the group's infos.
 */
class ConversationDisappearingController extends Controller
{
    /**
     * Durations offered by WhatsApp, in seconds.
     */
    public const DURATIONS = [86400, 604800, 7776000];

    public function update(Request $request, Conversation $conversation, ManageGroup $manageGroup): ConversationResource
    {
        $user = $request->user();

        Gate::authorize($conversation->isGroup() ? 'editGroupInfo' : 'sendMessage', $conversation);

        $validated = $request->validate([
            'seconds' => ['present', 'nullable', 'integer', Rule::in(self::DURATIONS)],
        ]);

        $seconds = $validated['seconds'] === null ? null : (int) $validated['seconds'];

        if ($seconds !== $conversation->disappearing_seconds) {
            $conversation->update(['disappearing_seconds' => $seconds]);
            $manageGroup->notice($conversation, $user, GroupEvent::DisappearingChanged, extra: ['value' => $seconds]);
        }

        $conversation->unsetRelation('participants');
        $conversation->load(Conversation::participantsWithBlockStateFor($user));

        return ConversationResource::make($conversation)->withMembers();
    }
}
