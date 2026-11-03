<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\AvatarThumbnail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProfilePhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->alice = User::factory()->create(['avatar_path' => null]);
        $this->bob = User::factory()->create();
    }

    private function uploadAvatar(string $name): string
    {
        $this->postJson('/api/v1/me/avatar', ['avatar' => UploadedFile::fake()->image($name)])->assertOk();

        return $this->alice->fresh()->avatar_path;
    }

    public function test_the_history_lists_every_photo_newest_first(): void
    {
        Sanctum::actingAs($this->alice, ['access']);
        $first = $this->uploadAvatar('first.jpg');
        $this->travel(1)->minute();
        $second = $this->uploadAvatar('second.jpg');

        Sanctum::actingAs($this->bob, ['access']);

        $this->getJson("/api/v1/users/{$this->alice->id}/profile-photos")
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonPath('0.url', Storage::disk('public')->url($second))
            ->assertJsonPath('0.is_current', true)
            ->assertJsonPath('1.url', Storage::disk('public')->url($first))
            ->assertJsonPath('1.is_current', false);
    }

    public function test_removing_the_photo_keeps_the_history_without_an_empty_entry(): void
    {
        Sanctum::actingAs($this->alice, ['access']);
        $path = $this->uploadAvatar('me.jpg');

        $this->deleteJson('/api/v1/me/avatar')
            ->assertOk()
            ->assertJsonPath('avatar_url', null);

        Storage::disk('public')->assertExists($path);
        $this->getJson("/api/v1/users/{$this->alice->id}/profile-photos")
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.is_current', false);
    }

    public function test_a_blocked_user_cannot_see_the_history(): void
    {
        $this->alice->blockedUsers()->attach($this->bob->id);
        Sanctum::actingAs($this->bob, ['access']);

        $this->getJson("/api/v1/users/{$this->alice->id}/profile-photos")->assertNotFound();
    }

    public function test_the_owner_removes_a_photo_from_the_history(): void
    {
        Sanctum::actingAs($this->alice, ['access']);
        $old = $this->uploadAvatar('old.jpg');
        $current = $this->uploadAvatar('current.jpg');
        $oldPhoto = $this->alice->profilePhotos()->where('path', $old)->sole();
        $currentPhoto = $this->alice->profilePhotos()->where('path', $current)->sole();

        $this->deleteJson("/api/v1/me/profile-photos/{$oldPhoto->id}")->assertNoContent();

        Storage::disk('public')->assertMissing($old);
        Storage::disk('public')->assertMissing(AvatarThumbnail::path($old));
        $this->assertSame($current, $this->alice->fresh()->avatar_path);

        $this->deleteJson("/api/v1/me/profile-photos/{$currentPhoto->id}")->assertNoContent();

        $this->assertNull($this->alice->fresh()->avatar_path);
        $this->assertSame(0, $this->alice->profilePhotos()->count());
    }

    public function test_nobody_else_removes_a_photo(): void
    {
        Sanctum::actingAs($this->alice, ['access']);
        $this->uploadAvatar('me.jpg');
        $photo = $this->alice->profilePhotos()->sole();

        Sanctum::actingAs($this->bob, ['access']);

        $this->deleteJson("/api/v1/me/profile-photos/{$photo->id}")->assertNotFound();
        $this->assertModelExists($photo);
    }
}
