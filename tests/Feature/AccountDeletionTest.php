<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->alice = User::factory()->create(['password' => 'secret-password']);
        $this->bob = User::factory()->create();
        $this->conversation = Conversation::factory()->between($this->alice, $this->bob)->create();

        Sanctum::actingAs($this->alice, ['access']);
    }

    public function test_the_account_and_its_content_are_deleted(): void
    {
        $this->alice->forceFill(['avatar_path' => UploadedFile::fake()->image('a.jpg')->store('avatars', 'public')])->save();
        $attachment = UploadedFile::fake()->image('photo.jpg')->store("chat-media/{$this->conversation->id}", 'public');
        $sent = Message::factory()->create([
            'conversation_id' => $this->conversation->id,
            'sender_id' => $this->alice->id,
            'file_path' => $attachment,
        ]);
        $received = Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->bob->id]);
        $this->alice->createToken('device', ['access']);

        $this->deleteJson('/api/v1/me', ['password' => 'secret-password'])->assertNoContent();

        $this->assertModelMissing($this->alice);
        $this->assertModelMissing($sent);
        $this->assertModelExists($received);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        Storage::disk('public')->assertMissing($attachment);
        Storage::disk('public')->assertDirectoryEmpty('avatars');
    }

    public function test_a_file_forwarded_by_someone_else_is_kept(): void
    {
        $attachment = UploadedFile::fake()->image('photo.jpg')->store("chat-media/{$this->conversation->id}", 'public');
        Message::factory()->create(['conversation_id' => $this->conversation->id, 'sender_id' => $this->alice->id, 'file_path' => $attachment]);
        $carol = User::factory()->create();
        $other = Conversation::factory()->between($this->bob, $carol)->create();
        Message::factory()->create(['conversation_id' => $other->id, 'sender_id' => $this->bob->id, 'file_path' => $attachment]);

        $this->deleteJson('/api/v1/me', ['password' => 'secret-password'])->assertNoContent();

        Storage::disk('public')->assertExists($attachment);
    }

    public function test_a_wrong_password_keeps_the_account(): void
    {
        $this->deleteJson('/api/v1/me', ['password' => 'wrong'])->assertJsonValidationErrors('password');

        $this->assertModelExists($this->alice);
    }

    public function test_the_deletion_instructions_are_public(): void
    {
        $this->get('/suppression-compte')
            ->assertOk()
            ->assertSee('Supprimer votre compte LoveGames')
            ->assertSee('georgesseni1@gmail.com');
    }
}
