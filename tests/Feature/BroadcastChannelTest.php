<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\LoveGame;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BroadcastChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The "null" test broadcaster authorizes everything: use one that runs the channel callbacks.
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'test', 'broadcasting.connections.reverb.secret' => 'secret', 'broadcasting.connections.reverb.app_id' => '1']);
        Broadcast::purge();
        require base_path('routes/channels.php');
    }

    /**
     * @return array<string, array{string, bool}>
     */
    public static function channels(): array
    {
        return [
            'own user channel' => ['users.{me}', true],
            'other user channel' => ['users.{other}', false],
            'own conversation' => ['conversations.{conversation}', true],
            'conversation of others' => ['conversations.{foreign_conversation}', false],
            'own game' => ['games.{game}', true],
            'game of others' => ['games.{foreign_game}', false],
            'any profile' => ['profiles.{other}', true],
        ];
    }

    #[DataProvider('channels')]
    public function test_private_channel_authorization(string $channel, bool $allowed): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $conversation = Conversation::factory()->between($me, $other)->create();
        $foreignConversation = Conversation::factory()->between($other, User::factory()->create())->create();
        $game = LoveGame::factory()->withPlayers([$me])->create();
        $foreignGame = LoveGame::factory()->withPlayers([$other])->create();

        $channelName = 'private-'.strtr($channel, [
            '{me}' => $me->id,
            '{other}' => $other->id,
            '{conversation}' => $conversation->id,
            '{foreign_conversation}' => $foreignConversation->id,
            '{game}' => $game->id,
            '{foreign_game}' => $foreignGame->id,
        ]);

        Sanctum::actingAs($me, ['access']);

        $response = $this->postJson('/api/v1/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channelName,
        ]);

        $allowed ? $response->assertOk() : $response->assertForbidden();
    }
}
