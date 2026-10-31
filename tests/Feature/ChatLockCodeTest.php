<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChatLockCodeTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = User::factory()->create();

        Sanctum::actingAs($this->alice, ['access']);
    }

    public function test_the_secret_code_is_stored_hashed_and_only_its_presence_is_exposed(): void
    {
        $this->getJson('/api/v1/me')->assertJsonPath('profile.has_chat_lock_code', false);

        $this->putJson('/api/v1/me/chat-lock-code', ['code' => '2580'])->assertNoContent();

        $hashedCode = $this->alice->fresh()->chat_lock_code;
        $this->assertNotSame('2580', $hashedCode);
        $this->assertTrue(Hash::check('2580', $hashedCode));

        $this->getJson('/api/v1/me')
            ->assertJsonPath('profile.has_chat_lock_code', true)
            ->assertJsonMissingPath('profile.chat_lock_code');
    }

    public function test_the_secret_code_must_have_between_4_and_32_characters(): void
    {
        $this->putJson('/api/v1/me/chat-lock-code', ['code' => '123'])->assertJsonValidationErrors('code');
        $this->putJson('/api/v1/me/chat-lock-code', ['code' => str_repeat('a', 33)])->assertJsonValidationErrors('code');
    }

    public function test_a_typed_code_is_verified(): void
    {
        $this->postJson('/api/v1/me/chat-lock-code/verify', ['code' => '2580'])->assertJsonPath('valid', false);

        $this->putJson('/api/v1/me/chat-lock-code', ['code' => '2580'])->assertNoContent();

        $this->postJson('/api/v1/me/chat-lock-code/verify', ['code' => '2580'])->assertJsonPath('valid', true);
        $this->postJson('/api/v1/me/chat-lock-code/verify', ['code' => '2581'])->assertJsonPath('valid', false);
    }

    public function test_the_secret_code_can_be_removed(): void
    {
        $this->putJson('/api/v1/me/chat-lock-code', ['code' => '2580'])->assertNoContent();
        $this->deleteJson('/api/v1/me/chat-lock-code')->assertNoContent();

        $this->assertNull($this->alice->fresh()->chat_lock_code);
        $this->postJson('/api/v1/me/chat-lock-code/verify', ['code' => '2580'])->assertJsonPath('valid', false);
    }

    public function test_code_guessing_is_throttled(): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $this->postJson('/api/v1/me/chat-lock-code/verify', ['code' => "guess{$attempt}"])->assertOk();
        }

        $this->postJson('/api/v1/me/chat-lock-code/verify', ['code' => 'guess'])->assertTooManyRequests();
    }

    public function test_other_users_never_learn_whether_a_secret_code_is_set(): void
    {
        $this->alice->update(['chat_lock_code' => '2580']);
        $bob = User::factory()->create();
        $conversation = Conversation::factory()->between($this->alice, $bob)->create();

        Sanctum::actingAs($bob, ['access']);

        $this->getJson("/api/v1/conversations/{$conversation->id}")
            ->assertJsonPath('participant.id', $this->alice->id)
            ->assertJsonMissingPath('participant.has_chat_lock_code');
    }
}
