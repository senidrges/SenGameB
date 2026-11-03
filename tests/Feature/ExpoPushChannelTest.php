<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ExpoPushChannelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Pushes go through the "deferred" queue: run them right away
        $this->withoutDefer();
    }

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

    public function test_other_expo_errors_are_logged(): void
    {
        Log::spy();
        Http::fake(['exp.host/*' => Http::response(['data' => [
            'status' => 'error',
            'message' => 'Unable to retrieve the FCM server key for the recipient\'s app.',
            'details' => ['error' => 'InvalidCredentials'],
        ]])]);
        [$recipient, $message] = $this->messageFor('ExponentPushToken[device]');

        $recipient->notify(new NewChatMessage($message));

        Log::shouldHaveReceived('warning')->once()->withArgs(
            fn (string $log, array $context) => $context['error'] === 'InvalidCredentials'
        );
        $this->assertSame('ExponentPushToken[device]', $recipient->fresh()->push_token);
    }

    public function test_users_without_token_are_skipped(): void
    {
        Http::fake();
        [$recipient, $message] = $this->messageFor(null);

        $recipient->notify(new NewChatMessage($message));

        Http::assertNothingSent();
    }

    public function test_android_pushes_with_buttons_go_straight_to_firebase(): void
    {
        $this->configureFirebase();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/love/messages/1']),
        ]);
        [$recipient, $message] = $this->messageFor('ExponentPushToken[device]', 'fcm-device');

        $recipient->notify(new NewChatMessage($message));

        Http::assertSent(fn (Request $request) => str_contains($request->url(), 'projects/love-games/messages:send')
            && $request->hasHeader('Authorization', 'Bearer google-token')
            && $request['message']['token'] === 'fcm-device'
            && $request['message']['data']['title'] === $message->sender->username
            && $request['message']['data']['message'] === 'Coucou'
            && $request['message']['data']['categoryId'] === NewChatMessage::CATEGORY
            && $request['message']['data']['channelId'] === 'messages'
            && json_decode($request['message']['data']['body'], true)['conversationId'] === $message->conversation_id
            && ! isset($request['message']['notification']));
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'exp.host'));
    }

    public function test_firebase_tokens_no_longer_registered_fall_back_to_expo(): void
    {
        $this->configureFirebase();
        Http::fake([
            'oauth2.googleapis.com/*' => Http::response(['access_token' => 'google-token']),
            'fcm.googleapis.com/*' => Http::response(['error' => ['details' => [['errorCode' => 'UNREGISTERED']]]], 404),
            'exp.host/*' => Http::response(['data' => ['status' => 'ok', 'id' => 'abc']]),
        ]);
        [$recipient, $message] = $this->messageFor('ExponentPushToken[device]', 'fcm-stale');

        $recipient->notify(new NewChatMessage($message));

        $this->assertNull($recipient->fresh()->fcm_token);
        Http::assertSent(fn (Request $request) => $request->url() === config('services.expo.push_url'));
    }

    public function test_without_firebase_credentials_expo_is_used(): void
    {
        Http::fake(['exp.host/*' => Http::response(['data' => ['status' => 'ok', 'id' => 'abc']])]);
        [$recipient, $message] = $this->messageFor('ExponentPushToken[device]', 'fcm-device');

        $recipient->notify(new NewChatMessage($message));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['to'] === 'ExponentPushToken[device]');
    }

    private function configureFirebase(): void
    {
        // Test-only key, unknown to Google
        $privateKey = file_get_contents(base_path('tests/Fixtures/firebase-test-key.pem'));

        $path = tempnam(sys_get_temp_dir(), 'fcm');
        file_put_contents($path, json_encode([
            'project_id' => 'love-games',
            'client_email' => 'push@love-games.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));
        $this->beforeApplicationDestroyed(fn () => @unlink($path));

        config(['services.fcm.credentials' => $path]);
    }

    /**
     * @return array{User, Message}
     */
    private function messageFor(?string $pushToken, ?string $fcmToken = null): array
    {
        $recipient = User::factory()->create(['push_token' => $pushToken, 'fcm_token' => $fcmToken]);
        $sender = User::factory()->create();
        $conversation = Conversation::factory()->between($recipient, $sender)->create();

        return [$recipient, Message::factory()->create([
            'conversation_id' => $conversation->id,
            'sender_id' => $sender->id,
            'content' => 'Coucou',
        ])];
    }
}
