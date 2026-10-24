<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReportTest extends TestCase
{
    use RefreshDatabase;

    private User $reporter;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reporter = User::factory()->create();
        $this->author = User::factory()->create();
        Sanctum::actingAs($this->reporter, ['access']);
    }

    public function test_the_reasons_are_listed_with_their_labels(): void
    {
        $this->getJson('/api/v1/report-reasons')
            ->assertOk()
            ->assertJsonFragment(['value' => 'harassment', 'label' => 'Harcèlement ou intimidation']);
    }

    public function test_reporting_an_account(): void
    {
        $this->postJson('/api/v1/reports', [
            'type' => 'user',
            'id' => $this->author->id,
            'reason' => 'fake_account',
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $report = Report::sole();
        $this->assertSame($this->author->id, $report->reported_user_id);
        $this->assertSame($this->author->username, $report->content_snapshot['username']);
    }

    public function test_reporting_a_message_keeps_a_copy_of_its_content(): void
    {
        $conversation = Conversation::factory()->between($this->reporter, $this->author)->create();
        $message = Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $this->author->id,
            'content' => 'Message insultant',
        ]);

        $this->postJson('/api/v1/reports', ['type' => 'message', 'id' => $message->id, 'reason' => 'harassment'])->assertCreated();

        $message->markDeletedForEveryone();

        $this->assertSame('Message insultant', Report::sole()->content_snapshot['content']);
    }

    public function test_reporting_a_game_message_and_a_game(): void
    {
        $game = LoveGame::factory()->withPlayers([$this->reporter])->create(['organizer_id' => $this->author->id]);
        $gameMessage = GameMessage::factory()->create(['game_id' => $game->id, 'sender_id' => $this->author->id]);

        $this->postJson('/api/v1/reports', ['type' => 'game_message', 'id' => $gameMessage->id, 'reason' => 'spam'])->assertCreated();
        $this->postJson('/api/v1/reports', ['type' => 'love_game', 'id' => $game->id, 'reason' => 'sexual_content'])->assertCreated();

        $this->assertSame(2, Report::query()->where('reported_user_id', $this->author->id)->count());
    }

    public function test_content_the_reporter_cannot_see_is_not_found(): void
    {
        $conversation = Conversation::factory()->between($this->author, User::factory()->create())->create();
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $this->author->id]);

        $this->postJson('/api/v1/reports', ['type' => 'message', 'id' => $message->id, 'reason' => 'spam'])->assertNotFound();
        $this->assertDatabaseCount('reports', 0);
    }

    public function test_users_cannot_report_themselves(): void
    {
        $this->postJson('/api/v1/reports', ['type' => 'user', 'id' => $this->reporter->id, 'reason' => 'spam'])
            ->assertJsonValidationErrors(['id' => 'Vous ne pouvez pas signaler votre propre compte ou contenu.']);
    }

    public function test_reporting_twice_keeps_a_single_pending_report(): void
    {
        $payload = ['type' => 'user', 'id' => $this->author->id, 'reason' => 'spam'];

        $this->postJson('/api/v1/reports', $payload)->assertCreated();
        $this->postJson('/api/v1/reports', $payload)->assertOk();

        $this->assertDatabaseCount('reports', 1);
    }

    public function test_other_reason_requires_details(): void
    {
        $this->postJson('/api/v1/reports', ['type' => 'user', 'id' => $this->author->id, 'reason' => 'other'])
            ->assertJsonValidationErrors(['details' => 'Décrivez le problème en quelques mots.']);
    }

    public function test_reporting_can_also_block_the_author(): void
    {
        $this->postJson('/api/v1/reports', [
            'type' => 'user',
            'id' => $this->author->id,
            'reason' => 'harassment',
            'also_block' => true,
        ])->assertCreated();

        $this->assertTrue($this->reporter->hasBlocked($this->author));
    }
}
