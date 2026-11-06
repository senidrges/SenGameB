<?php

namespace Tests\Feature;

use App\Events\GameMessagesReceipts;
use App\Events\UserTyping;
use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Models\User;
use App\Notifications\NewGameMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * The game chat works like a conversation: voice messages and other
 * attachments, polls, ticks, "is typing" and "is recording".
 */
class GameChatTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private User $player;

    private LoveGame $game;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->organizer = User::factory()->withPushToken()->create();
        $this->player = User::factory()->withPushToken()->create();
        $this->game = LoveGame::factory()->withPlayers([$this->player])->create(['organizer_id' => $this->organizer->id]);

        Sanctum::actingAs($this->player, ['access']);
    }

    public function test_a_voice_message_is_uploaded_then_sent(): void
    {
        Storage::fake('public');

        $upload = $this->postJson("/api/v1/games/{$this->game->id}/attachments", [
            'file' => UploadedFile::fake()->create('audio.m4a', 20, 'audio/mp4'),
        ])->assertCreated()->json();

        $this->postJson("/api/v1/games/{$this->game->id}/messages", [
            'message_type' => 'audio',
            'attachment_path' => $upload['path'],
            'file_size' => $upload['size'],
        ])
            ->assertCreated()
            ->assertJsonPath('message_type', 'audio')
            ->assertJsonPath('file_url', $upload['url'])
            ->assertJsonPath('is_delivered', false)
            ->assertJsonPath('is_read', false);

        Notification::assertSentTo(
            $this->organizer,
            NewGameMessage::class,
            fn (NewGameMessage $notification) => $notification->toExpo($this->organizer)->body === '🎤 Message vocal',
        );
    }

    public function test_attachments_of_another_chat_cannot_be_sent(): void
    {
        Storage::fake('public');
        $path = UploadedFile::fake()->image('photo.jpg')->store('chat-media/other', 'public');

        $this->postJson("/api/v1/games/{$this->game->id}/messages", [
            'message_type' => 'image',
            'attachment_path' => $path,
        ])->assertJsonValidationErrors('attachment_path');
    }

    public function test_outsiders_cannot_upload_to_the_game(): void
    {
        Storage::fake('public');
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->postJson("/api/v1/games/{$this->game->id}/attachments", [
            'file' => UploadedFile::fake()->image('photo.jpg'),
        ])->assertForbidden();
    }

    public function test_participants_vote_on_a_poll(): void
    {
        $pollId = $this->postJson("/api/v1/games/{$this->game->id}/messages", [
            'message_type' => 'poll',
            'payload' => ['question' => 'Où ?', 'options' => ['Plage', 'Cinéma']],
        ])->assertCreated()->json('id');

        Sanctum::actingAs($this->organizer, ['access']);
        $this->postJson("/api/v1/game-messages/{$pollId}/votes", ['option_ids' => ['o2']])
            ->assertOk()
            ->assertJsonPath('votes.o2', [$this->organizer->id]);
    }

    public function test_only_text_messages_can_be_edited(): void
    {
        $message = GameMessage::factory()->create([
            'game_id' => $this->game->id,
            'sender_id' => $this->player->id,
            'message_type' => 'location',
            'content' => null,
            'payload' => ['latitude' => 1, 'longitude' => 2],
        ]);

        $this->patchJson("/api/v1/game-messages/{$message->id}", ['content' => 'x'])->assertStatus(422);
    }

    public function test_messages_are_read_once_every_other_participant_read_them(): void
    {
        Event::fake([GameMessagesReceipts::class]);
        $secondPlayer = User::factory()->create();
        $this->game->participants()->attach($secondPlayer, ['role' => 'player']);

        $this->travel(-1)->minutes();
        $message = GameMessage::factory()->create(['game_id' => $this->game->id, 'sender_id' => $this->player->id]);
        $this->travelBack();

        // The organizer read it, the second player not yet: still unread
        Sanctum::actingAs($this->organizer, ['access']);
        $this->postJson("/api/v1/games/{$this->game->id}/read")->assertNoContent();
        $this->assertFalse($message->fresh()->is_read);

        // The second player's phone received it: delivered to everyone
        Sanctum::actingAs($secondPlayer, ['access']);
        $this->postJson('/api/v1/me/deliveries')->assertNoContent();
        $this->assertTrue($message->fresh()->is_delivered);
        $this->assertFalse($message->fresh()->is_read);

        $this->postJson("/api/v1/games/{$this->game->id}/read")->assertNoContent();
        $this->assertTrue($message->fresh()->is_read);

        Event::assertDispatched(GameMessagesReceipts::class, fn (GameMessagesReceipts $event) => $event->readIds === [$message->id]);
    }

    public function test_recording_a_voice_message_is_shown_to_the_others(): void
    {
        Event::fake([UserTyping::class]);

        $this->postJson("/api/v1/games/{$this->game->id}/typing", ['activity' => 'recording'])->assertNoContent();

        Event::assertDispatched(UserTyping::class, fn (UserTyping $event) => $event->inGame
            && $event->conversationId === $this->game->id
            && $event->activity === 'recording'
            && $event->recipientIds === [$this->organizer->id]
            && $event->broadcastAs() === 'game.typing'
            && $event->broadcastWith()['game_id'] === $this->game->id);
    }

    public function test_unknown_activities_are_refused(): void
    {
        $this->postJson("/api/v1/games/{$this->game->id}/typing", ['activity' => 'dancing'])
            ->assertJsonValidationErrors('activity');
    }
}
