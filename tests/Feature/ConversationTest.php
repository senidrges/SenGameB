<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConversationTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->withPushToken()->create();
        $this->conversation = Conversation::factory()->between($this->alice, $this->bob)->create();

        Sanctum::actingAs($this->alice, ['access']);
    }

    public function test_opening_a_conversation_reuses_the_existing_one(): void
    {
        $this->postJson('/api/v1/conversations', ['user_id' => $this->bob->id])
            ->assertOk()
            ->assertJsonPath('id', $this->conversation->id)
            ->assertJsonPath('participant.id', $this->bob->id);

        $carol = User::factory()->create();

        $this->postJson('/api/v1/conversations', ['user_id' => $carol->id])->assertCreated();
        $this->assertDatabaseCount('conversations', 2);
    }

    public function test_a_user_cannot_open_a_conversation_with_themselves(): void
    {
        $this->postJson('/api/v1/conversations', ['user_id' => $this->alice->id])
            ->assertJsonValidationErrors('user_id');
    }

    public function test_the_list_shows_the_other_participant_last_message_and_unread_count(): void
    {
        Message::factory()->count(2)->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);
        $this->travel(1)->minute();
        $last = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.participant.id', $this->bob->id)
            ->assertJsonPath('0.last_message.id', $last->id)
            ->assertJsonPath('0.unread_count', 2);
    }

    public function test_the_last_message_is_the_most_recent_whatever_its_uuid(): void
    {
        Message::factory()->create([
            'id' => 'ffffffff-ffff-4fff-bfff-ffffffffffff',
            'conversation_id' => $this->conversation->id,
            'created_at' => now()->subHour(),
        ]);
        $last = Message::factory()->create([
            'id' => '00000000-0000-4000-8000-000000000000',
            'conversation_id' => $this->conversation->id,
        ]);

        $this->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('0.last_message.id', $last->id);
    }

    public function test_outsiders_cannot_read_a_conversation(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")->assertForbidden();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => 'text',
            'content' => 'Hello',
        ])->assertForbidden();
    }

    public function test_sending_a_message_notifies_the_other_participant(): void
    {
        $parent = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => 'text',
            'content' => 'Coucou',
            'reply_to_id' => $parent->id,
        ])
            ->assertCreated()
            ->assertJsonPath('content', 'Coucou')
            ->assertJsonPath('sender.id', $this->alice->id)
            ->assertJsonPath('reply_to.id', $parent->id);

        Notification::assertSentTo($this->bob, NewChatMessage::class);
        Notification::assertNotSentTo($this->alice, NewChatMessage::class);
    }

    public function test_messages_are_paginated_oldest_first(): void
    {
        $older = Message::factory()->create(['conversation_id' => $this->conversation->id, 'created_at' => now()->subHour()]);
        $newer = Message::factory()->create(['conversation_id' => $this->conversation->id]);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")
            ->assertJsonPath('0.id', $older->id)
            ->assertJsonPath('1.id', $newer->id);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages?before=".urlencode($newer->created_at->toIso8601String()))
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $older->id);
    }

    public function test_attachments_are_uploaded_then_sent(): void
    {
        Storage::fake('public');

        $upload = $this->postJson("/api/v1/conversations/{$this->conversation->id}/attachments", [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertCreated()->json();

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => 'image',
            'attachment_path' => $upload['path'],
            'file_size' => $upload['size'],
        ])
            ->assertCreated()
            ->assertJsonPath('file_url', $upload['url'])
            ->assertJsonPath('content', null);
    }

    public function test_executable_attachments_are_refused(): void
    {
        Storage::fake('public');

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/attachments", [
            'file' => UploadedFile::fake()->createWithContent('shell.php', '<?php echo 1;'),
        ])->assertJsonValidationErrors('file');
    }

    public function test_attachments_of_another_conversation_cannot_be_sent(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('chat-media/other-conversation/photo.jpg', 'x');

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => 'image',
            'attachment_path' => 'chat-media/other-conversation/photo.jpg',
        ])->assertJsonValidationErrors(['attachment_path' => 'Le fichier joint est introuvable.']);
    }

    public function test_only_the_sender_edits_or_deletes_a_message(): void
    {
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);

        $this->patchJson("/api/v1/messages/{$message->id}", ['content' => 'Hacked'])->assertForbidden();
        $this->deleteJson("/api/v1/messages/{$message->id}")->assertForbidden();
    }

    public function test_editing_and_deleting_a_message(): void
    {
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);

        $this->patchJson("/api/v1/messages/{$message->id}", ['content' => 'Corrigé'])
            ->assertOk()
            ->assertJsonPath('content', 'Corrigé')
            ->assertJsonPath('edited_at', fn (?string $editedAt) => $editedAt !== null);

        $this->deleteJson("/api/v1/messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('is_deleted', true)
            ->assertJsonPath('content', null);

        $this->assertNull($message->fresh()->content);
    }

    public function test_reactions_are_toggled(): void
    {
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);

        $this->postJson("/api/v1/messages/{$message->id}/reactions", ['emoji' => '❤️'])
            ->assertOk()
            ->assertJsonPath('reactions', ['❤️' => [$this->alice->id]]);

        $this->postJson("/api/v1/messages/{$message->id}/reactions", ['emoji' => '❤️'])
            ->assertOk()
            ->assertJsonPath('reactions', null);
    }

    public function test_reading_marks_only_the_messages_of_the_others(): void
    {
        $received = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);
        $sent = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/read")->assertNoContent();

        $this->assertTrue($received->fresh()->is_read);
        $this->assertFalse($sent->fresh()->is_read);
    }

    public function test_forwarding_copies_the_message_to_another_conversation(): void
    {
        $carol = User::factory()->create();
        $target = Conversation::factory()->between($this->alice, $carol)->create();
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'content' => 'À transférer']);

        $this->postJson("/api/v1/messages/{$message->id}/forward", ['conversation_id' => $target->id])
            ->assertCreated()
            ->assertJsonPath('conversation_id', $target->id)
            ->assertJsonPath('content', 'À transférer');
    }

    public function test_forwarding_to_a_conversation_of_someone_else_is_refused(): void
    {
        $strangers = Conversation::factory()->between(User::factory()->create(), User::factory()->create())->create();
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id]);

        $this->postJson("/api/v1/messages/{$message->id}/forward", ['conversation_id' => $strangers->id])->assertForbidden();
    }
}
