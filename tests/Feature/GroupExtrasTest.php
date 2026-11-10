<?php

namespace Tests\Feature;

use App\Enums\GroupEvent;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\IncomingCall;
use App\Notifications\MissedCall;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Group calls, @mentions, disappearing messages and the discreet departure.
 */
class GroupExtrasTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $carol;

    private Conversation $group;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        config([
            'services.calls.providers' => ['livekit', 'p2p'],
            'services.livekit.url' => 'wss://lovegames.livekit.cloud',
            'services.livekit.api_key' => 'APIkey',
            'services.livekit.api_secret' => 'livekit-secret',
        ]);

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->withPushToken()->create();
        $this->carol = User::factory()->withPushToken()->create();
        $this->alice->contacts()->attach([$this->bob->id, $this->carol->id]);

        $id = $this->signIn($this->alice)->postJson('/api/v1/groups', [
            'name' => 'Famille',
            'member_ids' => [$this->bob->id, $this->carol->id],
        ])->json('id');
        $this->group = Conversation::findOrFail($id);
        Notification::fake();
    }

    private function signIn(User $user): static
    {
        Sanctum::actingAs($user, ['access']);

        return $this;
    }

    public function test_a_group_call_rings_every_member_and_ends_with_its_last_participant(): void
    {
        $callId = $this->postJson("/api/v1/conversations/{$this->group->id}/calls", ['type' => 'audio'])
            ->assertCreated()
            ->assertJsonPath('is_group', true)
            ->assertJsonPath('status', 'ongoing')
            ->assertJsonPath('group.name', 'Famille')
            ->assertJsonCount(3, 'participants')
            ->json('id');

        Notification::assertSentTo([$this->bob, $this->carol], IncomingCall::class);
        $this->getJson("/api/v1/calls/{$callId}/connection")->assertOk()->assertJsonPath('provider', 'livekit');

        $this->signIn($this->bob)->postJson("/api/v1/calls/{$callId}/accept")
            ->assertOk()
            ->assertJsonPath('connection.provider', 'livekit');
        $this->signIn($this->carol)->postJson("/api/v1/calls/{$callId}/decline")->assertNoContent();

        $this->signIn($this->alice)->getJson("/api/v1/conversations/{$this->group->id}")
            ->assertJsonPath('group.active_call.id', $callId)
            ->assertJsonPath('group.active_call.participant_count', 2);

        // Alice leaves: Bob stays, the call goes on
        $this->postJson("/api/v1/calls/{$callId}/end")->assertNoContent();
        $this->assertSame('ongoing', Call::find($callId)->status->value);

        // Carol joins late, from the group
        $this->signIn($this->carol)->postJson("/api/v1/calls/{$callId}/accept")->assertOk();
        $this->postJson("/api/v1/calls/{$callId}/end");
        $this->signIn($this->bob)->postJson("/api/v1/calls/{$callId}/end");

        $this->assertSame('ended', Call::find($callId)->status->value);
        $trace = $this->group->messages()->where('message_type', 'call')->first();
        $this->assertTrue($trace->payload['is_group']);
        $this->assertSame(3, $trace->payload['participant_count']);
    }

    public function test_a_group_call_nobody_answers_is_missed(): void
    {
        $callId = $this->postJson("/api/v1/conversations/{$this->group->id}/calls", ['type' => 'video'])->json('id');

        $this->postJson("/api/v1/calls/{$callId}/end")->assertNoContent();

        $this->assertSame('missed', Call::find($callId)->status->value);
        Notification::assertSentTo([$this->bob, $this->carol], MissedCall::class);
    }

    public function test_calling_a_group_with_a_call_going_on_joins_it(): void
    {
        $callId = $this->postJson("/api/v1/conversations/{$this->group->id}/calls", ['type' => 'audio'])->json('id');

        $this->signIn($this->bob)->postJson("/api/v1/conversations/{$this->group->id}/calls", ['type' => 'audio'])
            ->assertOk()
            ->assertJsonPath('id', $callId);
    }

    public function test_group_calls_need_livekit(): void
    {
        config(['services.livekit.api_secret' => null]);

        $this->postJson("/api/v1/conversations/{$this->group->id}/calls", ['type' => 'audio'])->assertUnprocessable();
    }

    public function test_mentioned_members_get_a_push_even_when_the_group_is_muted(): void
    {
        $this->signIn($this->bob)->postJson("/api/v1/conversations/{$this->group->id}/mute");
        $this->signIn($this->carol)->postJson("/api/v1/conversations/{$this->group->id}/mute");

        $this->signIn($this->alice)->postJson("/api/v1/conversations/{$this->group->id}/messages", [
            'message_type' => 'text',
            'content' => "@{$this->bob->username} tu viens ?",
            'mentions' => [$this->bob->id],
        ])->assertCreated()->assertJsonPath('mentions', [$this->bob->id]);

        Notification::assertSentTo($this->bob, NewChatMessage::class);
        Notification::assertNotSentTo($this->carol, NewChatMessage::class);

        $this->signIn($this->bob)->getJson('/api/v1/conversations')->assertJsonPath('0.unread_mentions_count', 1);
    }

    public function test_only_members_can_be_mentioned(): void
    {
        $stranger = User::factory()->create();

        $this->postJson("/api/v1/conversations/{$this->group->id}/messages", [
            'message_type' => 'text',
            'content' => 'Salut',
            'mentions' => [$stranger->id],
        ])->assertJsonValidationErrors('mentions.0');
    }

    public function test_disappearing_messages_go_after_their_time(): void
    {
        $this->putJson("/api/v1/conversations/{$this->group->id}/disappearing", ['seconds' => 86400])
            ->assertOk()
            ->assertJsonPath('disappearing_seconds', 86400);
        $notice = $this->group->messages()->where('message_type', 'system')->latest('created_at')->orderByDesc('id')->first();
        $this->assertSame(GroupEvent::DisappearingChanged->value, $notice->payload['event']);
        $this->assertNull($notice->expires_at);

        $id = $this->postJson("/api/v1/conversations/{$this->group->id}/messages", ['message_type' => 'text', 'content' => 'Bientôt parti'])
            ->assertCreated()
            ->json('id');
        $this->assertNotNull(Message::find($id)->expires_at);

        $this->travel(25)->hours();

        $ids = collect($this->getJson("/api/v1/conversations/{$this->group->id}/messages")->json())->pluck('id');
        $this->assertNotContains($id, $ids);
        $this->assertNull(Message::find($id));
        $this->assertNotNull($notice->fresh());

        $this->putJson("/api/v1/conversations/{$this->group->id}/disappearing", ['seconds' => 1234])
            ->assertJsonValidationErrors('seconds');
    }

    public function test_either_side_of_a_direct_chat_sets_disappearing_messages(): void
    {
        $direct = Conversation::factory()->between($this->alice, $this->bob)->create();

        $this->signIn($this->bob)->putJson("/api/v1/conversations/{$direct->id}/disappearing", ['seconds' => 604800])
            ->assertOk()
            ->assertJsonPath('disappearing_seconds', 604800);
        $this->putJson("/api/v1/conversations/{$direct->id}/disappearing", ['seconds' => null])
            ->assertOk()
            ->assertJsonPath('disappearing_seconds', null);
    }

    public function test_only_admins_see_that_someone_left(): void
    {
        $this->signIn($this->carol)->postJson("/api/v1/groups/{$this->group->id}/leave")->assertOk();

        $events = fn (User $user) => collect($this->signIn($user)->getJson("/api/v1/conversations/{$this->group->id}/messages")->json())
            ->pluck('payload.event')
            ->all();

        $this->assertContains(GroupEvent::Left->value, $events($this->alice));
        $this->assertNotContains(GroupEvent::Left->value, $events($this->bob));
        $this->assertContains(GroupEvent::Left->value, $events($this->carol));
    }
}
