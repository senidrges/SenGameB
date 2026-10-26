<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationArchiveAndLockTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->create();
        $this->conversation = Conversation::factory()->between($this->alice, $this->bob)->create();

        Sanctum::actingAs($this->alice, ['access']);
    }

    public function test_a_conversation_can_be_archived_and_unarchived_for_the_user_only(): void
    {
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/archive")->assertNoContent();

        $this->getJson('/api/v1/conversations')->assertJsonPath('0.is_archived', true);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}")->assertJsonPath('is_archived', true);

        Sanctum::actingAs($this->bob, ['access']);
        $this->getJson('/api/v1/conversations')->assertJsonPath('0.is_archived', false);

        Sanctum::actingAs($this->alice, ['access']);
        $this->deleteJson("/api/v1/conversations/{$this->conversation->id}/archive")->assertNoContent();
        $this->getJson('/api/v1/conversations')->assertJsonPath('0.is_archived', false);
    }

    public function test_an_archived_conversation_stays_archived_when_a_message_arrives(): void
    {
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/archive")->assertNoContent();

        Sanctum::actingAs($this->bob, ['access']);
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => 'text',
            'content' => 'Coucou',
        ])->assertCreated();

        Sanctum::actingAs($this->alice, ['access']);
        $this->getJson('/api/v1/conversations')->assertJsonPath('0.is_archived', true);
    }

    public function test_locking_a_conversation_takes_it_out_of_the_archive(): void
    {
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/archive")->assertNoContent();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/lock")->assertNoContent();

        $this->getJson('/api/v1/conversations')
            ->assertJsonPath('0.is_locked', true)
            ->assertJsonPath('0.is_archived', false);

        $this->deleteJson("/api/v1/conversations/{$this->conversation->id}/lock")->assertNoContent();
        $this->getJson('/api/v1/conversations')->assertJsonPath('0.is_locked', false);
    }

    public function test_outsiders_cannot_archive_or_lock_a_conversation(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/archive")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/lock")->assertForbidden();
    }

    public function test_push_notifications_of_a_locked_conversation_hide_the_sender_and_content(): void
    {
        $message = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->bob->id,
            'content' => 'Secret',
        ]);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/lock")->assertNoContent();

        $locked = (new NewChatMessage($message))->toExpo($this->alice);
        $this->assertSame('Nouveau message', $locked->body);
        $this->assertNull($locked->categoryId);
        $this->assertArrayNotHasKey('senderUsername', $locked->data);

        $visible = (new NewChatMessage($message))->toExpo($this->bob);
        $this->assertSame($this->bob->username, $visible->title);
    }
}
