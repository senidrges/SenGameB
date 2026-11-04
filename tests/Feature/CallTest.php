<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\CallType;
use App\Enums\MessageType;
use App\Events\CallChanged;
use App\Events\CallSignal;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\IncomingCall;
use App\Notifications\MissedCall;
use App\Services\Calls\HmacJwt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CallTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Event::fake([CallChanged::class, CallSignal::class]);

        config([
            'services.calls.providers' => ['livekit', 'p2p'],
            'services.livekit.url' => 'wss://lovegames.livekit.cloud',
            'services.livekit.api_key' => 'APIkey',
            'services.livekit.api_secret' => 'livekit-secret',
            'services.livekit.monthly_minutes' => 5000,
            'services.livekit.usage_limit' => 0.9,
        ]);

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->withPushToken()->create();
        $this->conversation = Conversation::factory()->between($this->alice, $this->bob)->create();
    }

    private function startCall(string $type = 'audio'): Call
    {
        Sanctum::actingAs($this->alice, ['access']);
        $id = $this->postJson("/api/v1/conversations/{$this->conversation->id}/calls", ['type' => $type])
            ->assertCreated()
            ->json('id');

        return Call::findOrFail($id);
    }

    private function answer(Call $call): void
    {
        Sanctum::actingAs($this->bob, ['access']);
        $this->postJson("/api/v1/calls/{$call->id}/accept")->assertOk();
    }

    public function test_a_call_rings_the_other_participant(): void
    {
        Sanctum::actingAs($this->alice, ['access']);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/calls", ['type' => 'video'])
            ->assertCreated()
            ->assertJsonPath('type', 'video')
            ->assertJsonPath('status', 'ringing')
            ->assertJsonPath('provider', 'livekit')
            ->assertJsonPath('caller.id', $this->alice->id)
            ->assertJsonPath('callee.id', $this->bob->id);

        Notification::assertSentTo($this->bob, IncomingCall::class, function (IncomingCall $notification) {
            $push = $notification->toExpo($this->bob);

            return $push->title === $this->alice->username
                && $push->body === 'Appel vidéo entrant'
                && $push->priority === 'high'
                && $push->categoryId === IncomingCall::CATEGORY
                && $push->data['callId'] === $notification->call->id;
        });
        Event::assertDispatched(CallChanged::class);
    }

    public function test_calls_go_peer_to_peer_without_livekit_settings(): void
    {
        config(['services.livekit.api_secret' => null]);

        $this->assertSame('p2p', $this->startCall()->provider);
    }

    public function test_calls_go_peer_to_peer_once_the_free_livekit_minutes_are_almost_used(): void
    {
        // 2 participants x 2250 minutes = 4500, the 90% limit of 5000
        Call::create([
            'conversation_id' => $this->conversation->id,
            'caller_id' => $this->alice->id,
            'callee_id' => $this->bob->id,
            'type' => 'audio',
            'provider' => 'livekit',
            'status' => CallStatus::Ended,
            'answered_at' => now()->startOfMonth(),
            'ended_at' => now()->startOfMonth()->addMinutes(2250),
        ]);

        $this->assertSame('p2p', $this->startCall()->provider);
    }

    public function test_the_order_of_providers_is_configurable(): void
    {
        config(['services.calls.providers' => ['p2p', 'livekit']]);

        $this->assertSame('p2p', $this->startCall()->provider);
    }

    public function test_a_blocked_user_cannot_call(): void
    {
        $this->bob->blockedUsers()->attach($this->alice->id);
        Sanctum::actingAs($this->alice, ['access']);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/calls", ['type' => 'audio'])->assertForbidden();
    }

    public function test_nobody_can_be_called_twice_at_the_same_time(): void
    {
        $this->startCall();
        $carol = User::factory()->create();
        $conversation = Conversation::factory()->between($carol, $this->bob)->create();
        Sanctum::actingAs($carol, ['access']);

        $this->postJson("/api/v1/conversations/{$conversation->id}/calls", ['type' => 'audio'])->assertConflict();
    }

    public function test_answering_gives_a_livekit_token_for_the_call_room(): void
    {
        $call = $this->startCall();
        Sanctum::actingAs($this->bob, ['access']);

        $response = $this->postJson("/api/v1/calls/{$call->id}/accept")
            ->assertOk()
            ->assertJsonPath('status', 'ongoing')
            ->assertJsonPath('connection.provider', 'livekit')
            ->assertJsonPath('connection.url', 'wss://lovegames.livekit.cloud');

        $claims = HmacJwt::decode($response->json('connection.token'), 'livekit-secret');
        $this->assertSame($this->bob->id, $claims['sub']);
        $this->assertSame($call->id, $claims['video']['room']);
        $this->assertNotNull($call->fresh()->answered_at);
    }

    public function test_only_the_callee_answers_and_only_while_it_rings(): void
    {
        $call = $this->startCall();

        $this->postJson("/api/v1/calls/{$call->id}/accept")->assertNotFound();

        $this->travel(Call::RING_SECONDS + 1)->seconds();
        Sanctum::actingAs($this->bob, ['access']);

        $this->postJson("/api/v1/calls/{$call->id}/accept")->assertConflict();
    }

    public function test_the_caller_gets_its_connection_once_answered(): void
    {
        config(['services.calls.providers' => ['p2p'], 'services.calls.turn_urls' => ['turn:turn.example.com:3478']]);
        $call = $this->startCall();

        $this->getJson("/api/v1/calls/{$call->id}/connection")->assertConflict();

        $this->answer($call);
        Sanctum::actingAs($this->alice, ['access']);

        $this->getJson("/api/v1/calls/{$call->id}/connection")
            ->assertOk()
            ->assertJsonPath('provider', 'p2p')
            ->assertJsonPath('is_offerer', true)
            ->assertJsonPath('ice_servers.1.urls.0', 'turn:turn.example.com:3478');
    }

    public function test_a_call_nobody_answered_in_time_shows_as_missed(): void
    {
        $call = $this->startCall();
        Sanctum::actingAs($this->bob, ['access']);

        $this->getJson("/api/v1/calls/{$call->id}")->assertOk()->assertJsonPath('status', 'ringing');

        $this->travel(Call::RING_SECONDS + 1)->seconds();

        $this->getJson("/api/v1/calls/{$call->id}")->assertOk()->assertJsonPath('status', 'missed');

        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->getJson("/api/v1/calls/{$call->id}")->assertNotFound();
    }

    public function test_declining_ends_the_call(): void
    {
        $call = $this->startCall();
        Sanctum::actingAs($this->bob, ['access']);

        $this->postJson("/api/v1/calls/{$call->id}/decline")->assertNoContent();

        $this->assertSame(CallStatus::Declined, $call->fresh()->status);
    }

    public function test_hanging_up_before_the_answer_is_a_missed_call(): void
    {
        $call = $this->startCall();

        $this->postJson("/api/v1/calls/{$call->id}/end")->assertNoContent();

        $this->assertSame(CallStatus::Missed, $call->fresh()->status);
        Notification::assertSentTo($this->bob, MissedCall::class);
    }

    public function test_hanging_up_after_the_answer_ends_the_call(): void
    {
        $call = $this->startCall();
        $this->answer($call);

        $this->postJson("/api/v1/calls/{$call->id}/end")->assertNoContent();

        $this->assertSame(CallStatus::Ended, $call->fresh()->status);
        $this->assertNotNull($call->fresh()->ended_at);
        Notification::assertNotSentTo($this->bob, MissedCall::class);
    }

    public function test_a_finished_call_leaves_a_trace_in_the_conversation(): void
    {
        $call = $this->startCall('video');
        $this->answer($call);
        $this->travel(95)->seconds();

        $this->postJson("/api/v1/calls/{$call->id}/end")->assertNoContent();
        // The other phone hangs up too: still a single trace
        Sanctum::actingAs($this->alice, ['access']);
        $this->postJson("/api/v1/calls/{$call->id}/end")->assertNoContent();

        $trace = $this->conversation->messages()->sole();
        $this->assertSame(MessageType::Call, $trace->message_type);
        $this->assertSame($this->alice->id, $trace->sender_id);
        $this->assertSame([
            'call_id' => $call->id,
            'call_type' => 'video',
            'status' => 'ended',
            'duration' => 95,
        ], $trace->payload);
        // Answered: nothing new for the callee
        $this->assertTrue($trace->is_read);
    }

    public function test_a_missed_call_stays_unread_in_the_conversation(): void
    {
        $call = $this->startCall();

        $this->postJson("/api/v1/calls/{$call->id}/end")->assertNoContent();

        $trace = $this->conversation->messages()->sole();
        $this->assertSame('missed', $trace->payload['status']);
        $this->assertSame(0, $trace->payload['duration']);
        $this->assertFalse($trace->is_read);
    }

    public function test_a_declined_call_leaves_a_trace_in_the_conversation(): void
    {
        $call = $this->startCall();
        Sanctum::actingAs($this->bob, ['access']);

        $this->postJson("/api/v1/calls/{$call->id}/decline")->assertNoContent();

        $this->assertSame('declined', $this->conversation->messages()->sole()->payload['status']);
    }

    public function test_an_audio_call_switches_to_video(): void
    {
        $call = $this->startCall();
        $this->answer($call);

        $this->postJson("/api/v1/calls/{$call->id}/video")
            ->assertOk()
            ->assertJsonPath('type', 'video');

        $this->assertSame(CallType::Video, $call->fresh()->type);
        Event::assertDispatched(CallChanged::class, fn (CallChanged $event) => $event->call->type === CallType::Video);
    }

    public function test_only_an_ongoing_call_switches_to_video(): void
    {
        $call = $this->startCall();

        $this->postJson("/api/v1/calls/{$call->id}/video")->assertConflict();

        Sanctum::actingAs(User::factory()->create(), ['access']);
        $this->postJson("/api/v1/calls/{$call->id}/video")->assertNotFound();
    }

    public function test_the_lock_screen_declines_through_the_signed_link_of_the_push(): void
    {
        $call = $this->startCall();
        $declineUrl = null;
        Notification::assertSentTo($this->bob, IncomingCall::class, function (IncomingCall $notification) use (&$declineUrl) {
            $declineUrl = $notification->toExpo($this->bob)->data['declineUrl'];

            return true;
        });

        $this->app['auth']->forgetGuards();
        $this->post($declineUrl)->assertNoContent();

        $this->assertSame(CallStatus::Declined, $call->fresh()->status);
    }

    public function test_the_decline_link_must_be_signed(): void
    {
        $call = $this->startCall();
        $this->app['auth']->forgetGuards();

        $this->post("/api/v1/calls/{$call->id}/notification-decline")->assertForbidden();

        $this->assertSame(CallStatus::Ringing, $call->fresh()->status);
    }

    public function test_users_cannot_write_call_traces_themselves(): void
    {
        Sanctum::actingAs($this->alice, ['access']);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => 'call',
            'payload' => ['status' => 'missed'],
        ])->assertUnprocessable();
    }

    public function test_peer_to_peer_details_are_passed_to_the_other_phone(): void
    {
        config(['services.calls.providers' => ['p2p']]);
        $call = $this->startCall();
        $this->answer($call);
        Sanctum::actingAs($this->alice, ['access']);

        $this->postJson("/api/v1/calls/{$call->id}/signals", ['type' => 'offer', 'sdp' => 'v=0'])->assertNoContent();

        Event::assertDispatched(CallSignal::class, fn (CallSignal $event) => $event->recipientId === $this->bob->id
            && $event->signal === ['type' => 'offer', 'sdp' => 'v=0']);
    }

    public function test_livekit_ends_the_call_when_its_room_closes(): void
    {
        $call = $this->startCall();
        $this->answer($call);
        $body = json_encode(['event' => 'room_finished', 'room' => ['name' => $call->id]]);
        $token = HmacJwt::encode([
            'iss' => 'APIkey',
            'exp' => now()->addMinute()->timestamp,
            'sha256' => base64_encode(hash('sha256', $body, true)),
        ], 'livekit-secret');

        $this->call('POST', '/api/v1/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => $token,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertNoContent();

        $this->assertSame(CallStatus::Ended, $call->fresh()->status);
    }

    public function test_unsigned_livekit_webhooks_are_refused(): void
    {
        $call = $this->startCall();
        $this->answer($call);
        $body = json_encode(['event' => 'room_finished', 'room' => ['name' => $call->id]]);
        $token = HmacJwt::encode(['iss' => 'APIkey', 'sha256' => base64_encode(hash('sha256', $body, true))], 'wrong-secret');

        $this->call('POST', '/api/v1/webhooks/livekit', [], [], [], [
            'HTTP_AUTHORIZATION' => $token,
            'CONTENT_TYPE' => 'application/json',
        ], $body)->assertUnauthorized();

        $this->assertSame(CallStatus::Ongoing, $call->fresh()->status);
    }
}
