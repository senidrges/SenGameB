<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AvatarThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_onboarding_creates_the_public_profile(): void
    {
        $user = User::factory()->withoutProfile()->create();
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/v1/me')->assertJsonPath('profile', null);

        $this->postJson('/api/v1/me/profile', [
            'username' => 'alice_92',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'phone_number' => '70123456',
            'biometrics_enabled' => true,
        ])
            ->assertOk()
            ->assertJsonPath('username', 'alice_92')
            ->assertJsonPath('phone_number', '+22670123456')
            ->assertJsonPath('first_login_completed', true);

        $this->getJson('/api/v1/me')->assertJsonPath('profile.username', 'alice_92');
    }

    public function test_onboarding_rejects_a_taken_username(): void
    {
        User::factory()->create(['username' => 'alice']);
        Sanctum::actingAs(User::factory()->withoutProfile()->create(), ['access']);

        $this->postJson('/api/v1/me/profile', [
            'username' => 'alice',
            'first_name' => 'Alice',
            'last_name' => 'Martin',
            'phone_number' => '70123456',
        ])->assertJsonValidationErrors(['username' => "Ce nom d'utilisateur est déjà pris."]);
    }

    public function test_profile_update_keeps_the_own_username(): void
    {
        $user = User::factory()->create(['username' => 'alice']);
        Sanctum::actingAs($user, ['access']);

        $this->patchJson('/api/v1/me', ['username' => 'alice', 'status' => 'En vacances'])
            ->assertOk()
            ->assertJsonPath('status', 'En vacances');
    }

    public function test_avatar_upload_keeps_the_previous_photo_in_the_history(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
        Storage::disk('public')->put('avatars/old.jpg', 'old');
        Sanctum::actingAs($user, ['access']);

        $response = $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('me.jpg')])->assertOk();

        $path = $user->fresh()->avatar_path;
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists('avatars/old.jpg');
        $response->assertJsonPath('avatar_url', Storage::disk('public')->url($path));
        $this->assertSame([$path], $user->profilePhotos()->pluck('path')->all());
    }

    public function test_avatar_upload_creates_a_small_thumbnail_for_the_lists(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['avatar_path' => null]);
        Sanctum::actingAs($user, ['access']);

        $response = $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image('me.png', 1024, 768)])->assertOk();

        $thumbnail = AvatarThumbnail::path($user->fresh()->avatar_path);
        Storage::disk('public')->assertExists($thumbnail);
        $this->assertSame([160, 160], array_slice(getimagesizefromstring(Storage::disk('public')->get($thumbnail)), 0, 2));
        $response->assertJsonPath('avatar_thumb_url', Storage::disk('public')->url($thumbnail));
    }

    public function test_a_photo_uploaded_before_thumbnails_gets_one_when_first_shown(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old.jpg', UploadedFile::fake()->image('old.jpg')->getContent());
        $user = User::factory()->create(['avatar_path' => 'avatars/old.jpg']);
        Sanctum::actingAs($user, ['access']);

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('avatar_thumb_url', Storage::disk('public')->url('avatars/thumbs/old.jpg'));

        Storage::disk('public')->assertExists('avatars/thumbs/old.jpg');
    }

    public function test_the_full_photo_is_used_when_no_thumbnail_can_be_made(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/broken.jpg', 'not an image');
        $user = User::factory()->create(['avatar_path' => 'avatars/broken.jpg']);
        Sanctum::actingAs($user, ['access']);

        $this->getJson("/api/v1/users/{$user->id}")
            ->assertOk()
            ->assertJsonPath('avatar_thumb_url', Storage::disk('public')->url('avatars/broken.jpg'));
    }

    public function test_a_push_token_belongs_to_a_single_account(): void
    {
        $previousOwner = User::factory()->create(['push_token' => 'ExponentPushToken[abc]']);
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['access']);

        $this->putJson('/api/v1/me/push-token', ['token' => 'ExponentPushToken[abc]'])->assertNoContent();

        $this->assertNull($previousOwner->fresh()->push_token);
        $this->assertSame('ExponentPushToken[abc]', $user->fresh()->push_token);
    }

    public function test_users_are_searched_by_username_without_the_current_user(): void
    {
        $user = User::factory()->create(['username' => 'marie_me']);
        User::factory()->create(['username' => 'marie_lou']);
        User::factory()->withoutProfile()->create();
        Sanctum::actingAs($user, ['access']);

        $this->getJson('/api/v1/users?search=marie')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.username', 'marie_lou');
    }

    public function test_contacts_are_matched_on_normalized_phone_numbers(): void
    {
        $friend = User::factory()->create(['phone_number' => '+22670123456']);
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->postJson('/api/v1/contacts/match', ['phone_numbers' => ['70 12 34 56', '0022676999999']])
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $friend->id);
    }

    public function test_username_availability(): void
    {
        User::factory()->create(['username' => 'alice']);
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson('/api/v1/username-availability?username=alice')->assertExactJson(['available' => false]);
        $this->getJson('/api/v1/username-availability?username=bob')->assertExactJson(['available' => true]);
    }
}
