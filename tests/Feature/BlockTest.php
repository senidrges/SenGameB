<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BlockTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->alice = User::factory()->create(['username' => 'alice']);
        $this->bob = User::factory()->create(['username' => 'bob', 'phone_number' => '+22671222222']);
        $this->conversation = Conversation::factory()->between($this->alice, $this->bob)->create();
    }

    public function test_blocking_and_unblocking_an_account(): void
    {
        Sanctum::actingAs($this->alice, ['access']);

        $this->postJson('/api/v1/blocks', ['user_id' => $this->bob->id])->assertNoContent();
        $this->postJson('/api/v1/blocks', ['user_id' => $this->bob->id])->assertNoContent();

        $this->getJson('/api/v1/blocks')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->bob->id)
            ->assertJsonPath('0.blocked_at', fn (?string $blockedAt) => $blockedAt !== null);

        $this->deleteJson("/api/v1/blocks/{$this->bob->id}")->assertNoContent();
        $this->getJson('/api/v1/blocks')->assertJsonCount(0);
    }

    public function test_a_user_cannot_block_themselves(): void
    {
        Sanctum::actingAs($this->alice, ['access']);

        $this->postJson('/api/v1/blocks', ['user_id' => $this->alice->id])
            ->assertJsonValidationErrors(['user_id' => 'Vous ne pouvez pas vous bloquer vous-même.']);
    }

    public function test_neither_side_can_write_after_a_block(): void
    {
        $this->alice->blockedUsers()->attach($this->bob->id);

        foreach ([$this->alice, $this->bob] as $user) {
            Sanctum::actingAs($user, ['access']);

            $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
                'message_type' => 'text',
                'content' => 'Hello',
            ])
                ->assertForbidden()
                ->assertJsonPath('message', 'Vous ne pouvez pas écrire dans cette conversation.');
        }

        $this->assertDatabaseCount('messages', 0);
    }

    public function test_blocked_users_cannot_send_files_react_or_forward(): void
    {
        Storage::fake('public');
        $carol = User::factory()->create();
        $carolConversation = Conversation::factory()->between($this->bob, $carol)->create();
        $message = Message::factory()->create(['conversation_id' => $carolConversation->id, 'sender_id' => $carol->id]);
        $aliceMessage = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);
        $this->alice->blockedUsers()->attach($this->bob->id);
        Sanctum::actingAs($this->bob, ['access']);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/attachments", [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertForbidden();
        $this->postJson("/api/v1/messages/{$aliceMessage->id}/reactions", ['emoji' => '👍'])->assertForbidden();
        $this->postJson("/api/v1/messages/{$message->id}/forward", ['conversation_id' => $this->conversation->id])->assertForbidden();
    }

    public function test_the_history_stays_readable_and_the_conversation_says_writing_is_blocked(): void
    {
        Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);
        $this->alice->blockedUsers()->attach($this->bob->id);

        Sanctum::actingAs($this->alice, ['access']);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")->assertOk()->assertJsonCount(1);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}")
            ->assertOk()
            ->assertJsonPath('blocked_by_me', true)
            ->assertJsonPath('can_message', false);
        $this->getJson('/api/v1/conversations')
            ->assertJsonPath('0.blocked_by_me', true)
            ->assertJsonPath('0.can_message', false);

        Sanctum::actingAs($this->bob, ['access']);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}")
            ->assertJsonPath('blocked_by_me', false)
            ->assertJsonPath('can_message', false);
    }

    public function test_conversations_without_block_allow_writing(): void
    {
        Sanctum::actingAs($this->alice, ['access']);

        $this->getJson('/api/v1/conversations')
            ->assertJsonPath('0.blocked_by_me', false)
            ->assertJsonPath('0.can_message', true);
    }

    public function test_blocked_users_cannot_find_each_other(): void
    {
        $this->bob->blockedUsers()->attach($this->alice->id);
        Sanctum::actingAs($this->alice, ['access']);

        $this->getJson('/api/v1/users?search=bob')->assertJsonCount(0);
        $this->getJson("/api/v1/users/{$this->bob->id}")->assertNotFound();
        $this->postJson('/api/v1/contacts/match', ['phone_numbers' => ['71 22 22 22']])->assertJsonCount(0);
    }

    public function test_blocked_users_cannot_be_invited_to_a_game(): void
    {
        $this->bob->blockedUsers()->attach($this->alice->id);
        Sanctum::actingAs($this->alice, ['access']);

        $this->postJson('/api/v1/games', [
            'title' => 'Soirée',
            'player_ids' => [$this->bob->id],
            'items' => [['number' => 1, 'content' => 'Un cadeau']],
        ])->assertJsonValidationErrors(['player_ids.0' => 'Vous ne pouvez pas inviter cet utilisateur.']);
    }
}
