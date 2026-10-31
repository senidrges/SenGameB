<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationMuteTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create(['push_token' => 'ExponentPushToken[alice]']);
        $this->bob = User::factory()->create();
        $this->conversation = Conversation::factory()->between($this->alice, $this->bob)->create();

        Sanctum::actingAs($this->alice, ['access']);
    }

    public function test_a_conversation_can_be_muted_and_unmuted_for_the_user_only(): void
    {
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/mute", ['hours' => 8])->assertNoContent();

        $this->getJson('/api/v1/conversations')
            ->assertJsonPath('0.is_muted', true)
            ->assertJsonPath('0.muted_until', fn (string $until) => now()->addHours(8)->isSameMinute($until));

        Sanctum::actingAs($this->bob, ['access']);
        $this->getJson('/api/v1/conversations')->assertJsonPath('0.is_muted', false);

        Sanctum::actingAs($this->alice, ['access']);
        $this->deleteJson("/api/v1/conversations/{$this->conversation->id}/mute")->assertNoContent();
        $this->getJson('/api/v1/conversations')
            ->assertJsonPath('0.is_muted', false)
            ->assertJsonPath('0.muted_until', null);
    }

    public function test_a_conversation_muted_without_duration_stays_muted(): void
    {
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/mute")->assertNoContent();

        $this->travel(1)->years();

        $this->getJson('/api/v1/conversations')
            ->assertJsonPath('0.is_muted', true)
            ->assertJsonPath('0.muted_until', null);
    }

    public function test_a_muted_conversation_sends_no_push_until_the_mute_ends(): void
    {
        Notification::fake();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/mute", ['hours' => 8])->assertNoContent();

        Sanctum::actingAs($this->bob, ['access']);
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => 'text',
            'content' => 'Coucou',
        ])->assertCreated();

        Notification::assertNotSentTo($this->alice, NewChatMessage::class);

        $this->travel(9)->hours();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => 'text',
            'content' => 'Tu es là ?',
        ])->assertCreated();

        Notification::assertSentTo($this->alice, NewChatMessage::class);

        Sanctum::actingAs($this->alice, ['access']);
        $this->getJson('/api/v1/conversations')->assertJsonPath('0.is_muted', false);
    }

    public function test_outsiders_cannot_mute_a_conversation(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/mute")->assertForbidden();
    }
}
