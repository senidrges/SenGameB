<?php

namespace Tests\Feature;

use App\Events\MessagesDelivered;
use App\Events\MessagesRead;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
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

        // Soft delete: the content stays in the database
        $deleted = $message->fresh();
        $this->assertSame('Corrigé', $deleted->content);
        $this->assertNotNull($deleted->deleted_at);

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")
            ->assertJsonPath('0.is_deleted', true)
            ->assertJsonPath('0.content', null);
    }

    public function test_a_message_deleted_for_everyone_shows_as_deleted_in_the_list(): void
    {
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);

        $this->deleteJson("/api/v1/messages/{$message->id}")->assertOk();

        $this->getJson('/api/v1/conversations')
            ->assertJsonPath('0.last_message.id', $message->id)
            ->assertJsonPath('0.last_message.is_deleted', true)
            ->assertJsonPath('0.last_message.content', null);
    }

    public function test_a_message_deleted_for_me_is_hidden_from_me_only(): void
    {
        $older = Message::factory()->create(['conversation_id' => $this->conversation->id, 'created_at' => now()->subMinute()]);
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);

        $this->postJson("/api/v1/messages/{$message->id}/hide")->assertNoContent();
        $this->postJson("/api/v1/messages/{$message->id}/hide")->assertNoContent();

        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $older->id);
        $this->getJson('/api/v1/conversations')->assertJsonPath('0.last_message.id', $older->id);
        $this->assertDatabaseHas('messages', ['id' => $message->id]);

        Sanctum::actingAs($this->bob, ['access']);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")->assertJsonCount(2);
    }

    public function test_outsiders_cannot_hide_a_message(): void
    {
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id]);
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->postJson("/api/v1/messages/{$message->id}/hide")->assertForbidden();
    }

    public function test_deleting_a_conversation_hides_it_until_a_new_message(): void
    {
        $old = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);

        $this->travel(1)->minute();
        $this->deleteJson("/api/v1/conversations/{$this->conversation->id}")->assertNoContent();

        $this->getJson('/api/v1/conversations')->assertJsonCount(0);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")->assertJsonCount(0);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}")->assertJsonPath('last_message', null);

        // Nothing is erased, and the other participant keeps everything
        $this->assertDatabaseHas('messages', ['id' => $old->id]);
        Sanctum::actingAs($this->bob, ['access']);
        $this->getJson('/api/v1/conversations')->assertJsonCount(1)->assertJsonPath('0.last_message.id', $old->id);

        $this->travel(1)->minute();
        $new = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);

        Sanctum::actingAs($this->alice, ['access']);
        $this->getJson('/api/v1/conversations')
            ->assertJsonCount(1)
            ->assertJsonPath('0.last_message.id', $new->id)
            ->assertJsonPath('0.unread_count', 1);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $new->id);
    }

    public function test_outsiders_cannot_delete_a_conversation(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->deleteJson("/api/v1/conversations/{$this->conversation->id}")->assertForbidden();
    }

    public function test_a_deleted_message_cannot_get_reactions(): void
    {
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id, 'is_deleted' => true]);

        $this->postJson("/api/v1/messages/{$message->id}/reactions", ['emoji' => '❤️'])->assertStatus(422);
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
        $this->assertTrue($received->fresh()->is_delivered);
        $this->assertFalse($sent->fresh()->is_read);
    }

    public function test_reading_tells_the_senders_on_their_own_channel(): void
    {
        Event::fake([MessagesRead::class]);
        Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/read")->assertNoContent();
        $this->postJson("/api/v1/conversations/{$this->conversation->id}/read")->assertNoContent();

        Event::assertDispatchedTimes(MessagesRead::class, 1);
        Event::assertDispatched(MessagesRead::class, fn (MessagesRead $event) => $event->senderIds === [$this->bob->id]
            && in_array("private-users.{$this->bob->id}", array_map('strval', $event->broadcastOn()), true));
    }

    public function test_receiving_marks_the_messages_sent_to_me_as_delivered(): void
    {
        Event::fake([MessagesDelivered::class]);
        $received = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);
        $sent = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id]);
        $elsewhere = Message::factory()->create();

        $this->postJson('/api/v1/me/deliveries')->assertNoContent();

        $this->assertTrue($received->fresh()->is_delivered);
        $this->assertFalse($received->fresh()->is_read);
        $this->assertFalse($sent->fresh()->is_delivered);
        $this->assertFalse($elsewhere->fresh()->is_delivered);
        Event::assertDispatched(MessagesDelivered::class, fn (MessagesDelivered $event) => $event->conversationId === $this->conversation->id
            && $event->recipientId === $this->alice->id
            && $event->senderIds === [$this->bob->id]);

        $messages = collect($this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")->json())->keyBy('id');
        $this->assertTrue($messages[$received->id]['is_delivered']);
        $this->assertFalse($messages[$sent->id]['is_delivered']);
    }

    public function test_typing_is_sent_to_the_other_participant(): void
    {
        Event::fake([UserTyping::class]);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/typing")->assertNoContent();

        Event::assertDispatched(UserTyping::class, fn (UserTyping $event) => $event->conversationId === $this->conversation->id
            && $event->userId === $this->alice->id
            && $event->recipientIds === [$this->bob->id]);
    }

    public function test_typing_is_not_sent_across_a_block(): void
    {
        Event::fake([UserTyping::class]);
        $this->bob->blockedUsers()->attach($this->alice);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/typing")->assertNoContent();

        Event::assertNotDispatched(UserTyping::class);
    }

    public function test_outsiders_cannot_send_typing(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->postJson("/api/v1/conversations/{$this->conversation->id}/typing")->assertForbidden();
    }

    public function test_forwarding_copies_the_message_to_another_conversation(): void
    {
        $carol = User::factory()->create();
        $target = Conversation::factory()->between($this->alice, $carol)->create();
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'content' => 'À transférer']);

        $this->postJson("/api/v1/messages/{$message->id}/forward", ['conversation_id' => $target->id])
            ->assertCreated()
            ->assertJsonPath('conversation_id', $target->id)
            ->assertJsonPath('sender_id', $this->alice->id)
            ->assertJsonPath('content', 'À transférer')
            ->assertJsonPath('is_forwarded', true);

        $this->assertDatabaseHas('messages', ['conversation_id' => $target->id, 'forwarded_from_id' => $message->id]);
        $this->getJson("/api/v1/conversations/{$this->conversation->id}/messages")->assertJsonPath('0.is_forwarded', false);
    }

    public function test_a_deleted_message_cannot_be_forwarded(): void
    {
        $carol = User::factory()->create();
        $target = Conversation::factory()->between($this->alice, $carol)->create();
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id, 'is_deleted' => true]);

        $this->postJson("/api/v1/messages/{$message->id}/forward", ['conversation_id' => $target->id])->assertStatus(422);
    }

    public function test_forwarding_to_a_conversation_of_someone_else_is_refused(): void
    {
        $strangers = Conversation::factory()->between(User::factory()->create(), User::factory()->create())->create();
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id]);

        $this->postJson("/api/v1/messages/{$message->id}/forward", ['conversation_id' => $strangers->id])->assertForbidden();
    }
}
