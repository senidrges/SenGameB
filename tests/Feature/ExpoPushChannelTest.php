<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ExpoPushChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_chat_messages_are_pushed_through_expo(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => ['status' => 'ok', 'id' => 'abc']])]);
        [$recipient, $message] = $this->messageFor('ExponentPushToken[device]');

        $recipient->notify(new NewChatMessage($message));

        Http::assertSent(fn (Request $request) => $request['to'] === 'ExponentPushToken[device]'
            && $request['title'] === $message->sender->username
            && $request['body'] === 'Coucou'
            && $request['categoryId'] === NewChatMessage::CATEGORY
            && data_get($request->data(), 'data.conversationId') === $message->conversation_id);
    }

    public function test_tokens_expo_no_longer_knows_are_forgotten(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => [
            'status' => 'error',
            'details' => ['error' => 'DeviceNotRegistered'],
        ]])]);
        [$recipient, $message] = $this->messageFor('ExponentPushToken[stale]');

        $recipient->notify(new NewChatMessage($message));

        $this->assertNull($recipient->fresh()->push_token);
    }

    public function test_users_without_token_are_skipped(): void
    {
        Http::fake();
        [$recipient, $message] = $this->messageFor(null);

        $recipient->notify(new NewChatMessage($message));

        Http::assertNothingSent();
    }

    /**
     * @return array{User, Message}
     */
    private function messageFor(?string $pushToken): array
    {
        $recipient = User::factory()->create(['push_token' => $pushToken]);
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->between($recipient, $sender)->create();

        return [$recipient, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => 'Coucou',
        ])];
    }
}
