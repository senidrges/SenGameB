<?php

namespace Tests\Feature;

use App\Enums\GameStatus;
use App\Models\GameChoice;
use App\Models\LoveGame;
use App\Models\User;
use App\Notifications\GameActivated;
use App\Notifications\GameChoiceMade;
use App\Notifications\GameCreated;
use App\Notifications\NewGameMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoveGameTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private User $player;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->organizer = User::factory()->withPushToken()->create();
        $this->player = User::factory()->withPushToken()->create();
    }

    public function test_creating_a_game_registers_players_items_and_notifies_them(): void
    {
        Sanctum::actingAs($this->organizer, ['access']);

        $this->postJson('/api/v1/games', [
            'title' => 'Soirée surprise',
            'player_ids' => [$this->player->id],
            'items' => [
                ['number' => 1, 'content' => 'Un massage'],
                ['number' => 2, 'content' => 'Un dîner'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('status', 'waiting')
            ->assertJsonPath('organizer.id', $this->organizer->id)
            ->assertJsonCount(2, 'participants')
            ->assertJsonCount(2, 'items');

        Notification::assertSentTo($this->player, GameCreated::class);
        Notification::assertNotSentTo($this->organizer, GameCreated::class);
    }

    public function test_game_creation_validates_items(): void
    {
        Sanctum::actingAs($this->organizer, ['access']);

        $this->postJson('/api/v1/games', [
            'title' => 'Soirée',
            'player_ids' => [$this->player->id],
            'items' => [['number' => 1, 'content' => 'A'], ['number' => 1, 'content' => 'B']],
        ])->assertJsonValidationErrors(['items.0.number', 'items.1.number']);
    }

    public function test_outsiders_cannot_see_a_game(): void
    {
        $game = $this->createGame();
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson("/api/v1/games/{$game->id}")->assertForbidden();
    }

    public function test_the_games_list_only_contains_the_games_of_the_user(): void
    {
        $game = $this->createGame();
        LoveGame::factory()->withPlayers([User::factory()->create()])->create();
        Sanctum::actingAs($this->player, ['access']);

        $this->getJson('/api/v1/games')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $game->id)
            ->assertJsonPath('0.participants.1.role', 'player');
    }

    public function test_players_cannot_manage_the_game(): void
    {
        $game = $this->createGame();
        Sanctum::actingAs($this->player, ['access']);

        $this->postJson("/api/v1/games/{$game->id}/activation")->assertForbidden();
        $this->postJson("/api/v1/games/{$game->id}/reset")->assertForbidden();
        $this->putJson("/api/v1/games/{$game->id}/items", ['items' => [['number' => 1, 'content' => 'X']]])->assertForbidden();
        $this->deleteJson("/api/v1/games/{$game->id}")->assertForbidden();
    }

    public function test_activation_notifies_the_players(): void
    {
        $game = $this->createGame();
        Sanctum::actingAs($this->organizer, ['access']);

        $this->postJson("/api/v1/games/{$game->id}/activation")
            ->assertOk()
            ->assertJsonPath('status', 'active');

        Notification::assertSentTo($this->player, GameActivated::class);
    }

    public function test_players_cannot_choose_before_activation(): void
    {
        $game = $this->createGame();
        Sanctum::actingAs($this->player, ['access']);

        $this->postJson("/api/v1/games/{$game->id}/choices", ['choice_number' => 1])->assertForbidden();
    }

    public function test_the_game_completes_once_every_player_chose(): void
    {
        $secondPlayer = User::factory()->create();
        $game = LoveGame::factory()->active()->withPlayers([$this->player, $secondPlayer])->create(['organizer_id' => $this->organizer->id]);

        Sanctum::actingAs($this->player, ['access']);
        $this->postJson("/api/v1/games/{$game->id}/choices", ['choice_number' => 2])
            ->assertOk()
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('choices.0.choice_number', 2);

        Notification::assertSentTo($this->organizer, GameChoiceMade::class);

        Sanctum::actingAs($secondPlayer, ['access']);
        $this->postJson("/api/v1/games/{$game->id}/choices", ['choice_number' => 3])
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_a_choice_must_be_one_of_the_game_numbers(): void
    {
        $game = LoveGame::factory()->active()->withPlayers([$this->player], itemCount: 3)->create(['organizer_id' => $this->organizer->id]);
        Sanctum::actingAs($this->player, ['access']);

        $this->postJson("/api/v1/games/{$game->id}/choices", ['choice_number' => 9])->assertJsonValidationErrors('choice_number');
    }

    public function test_reset_clears_the_choices(): void
    {
        $game = LoveGame::factory()->active()->withPlayers([$this->player])->create(['organizer_id' => $this->organizer->id, 'status' => GameStatus::Completed]);
        GameChoice::factory()->create(['game_id' => $game->id, 'user_id' => $this->player->id]);
        Sanctum::actingAs($this->organizer, ['access']);

        $this->postJson("/api/v1/games/{$game->id}/reset")
            ->assertOk()
            ->assertJsonPath('status', 'waiting')
            ->assertJsonCount(0, 'choices');
    }

    public function test_the_organizer_replaces_the_items(): void
    {
        $game = $this->createGame();
        Sanctum::actingAs($this->organizer, ['access']);

        $this->putJson("/api/v1/games/{$game->id}/items", ['items' => [['number' => 5, 'content' => 'Nouveau']]])
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.number', 5);

        $this->assertSame(1, $game->items()->count());
    }

    public function test_game_chat_messages_notify_the_other_participants(): void
    {
        $game = $this->createGame();
        Sanctum::actingAs($this->player, ['access']);

        $this->postJson("/api/v1/games/{$game->id}/messages", ['content' => 'Trop hâte !'])
            ->assertCreated()
            ->assertJsonPath('sender.id', $this->player->id);

        Notification::assertSentTo($this->organizer, NewGameMessage::class);
        Notification::assertNotSentTo($this->player, NewGameMessage::class);
    }

    public function test_the_organizer_deletes_the_game(): void
    {
        $game = $this->createGame();
        Sanctum::actingAs($this->organizer, ['access']);

        $this->deleteJson("/api/v1/games/{$game->id}")->assertNoContent();

        $this->assertModelMissing($game);
    }

    private function createGame(): LoveGame
    {
        return LoveGame::factory()->withPlayers([$this->player])->create(['organizer_id' => $this->organizer->id]);
    }
}
