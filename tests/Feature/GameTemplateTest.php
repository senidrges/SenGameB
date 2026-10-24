<?php

namespace Tests\Feature;

use App\Models\GameTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_only_see_published_templates(): void
    {
        $published = GameTemplate::factory()->published()->create();
        GameTemplate::factory()->create();
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson('/api/v1/game-templates')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $published->id);
    }

    public function test_only_admins_manage_templates(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson('/api/v1/admin/game-templates')->assertForbidden();
    }

    public function test_admins_create_update_and_delete_templates(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create(), ['access']);

        $id = $this->postJson('/api/v1/admin/game-templates', [
            'title' => 'Saint-Valentin',
            'category' => 'Romantique',
            'items' => [['number' => 1, 'content' => 'Un bouquet']],
        ])
            ->assertCreated()
            ->assertJsonPath('description', '')
            ->assertJsonPath('is_published', false)
            ->json('id');

        $this->patchJson("/api/v1/admin/game-templates/{$id}", ['is_published' => true])
            ->assertOk()
            ->assertJsonPath('is_published', true)
            ->assertJsonPath('items.0.content', 'Un bouquet');

        $this->deleteJson("/api/v1/admin/game-templates/{$id}")->assertNoContent();
        $this->assertDatabaseCount('game_templates', 0);
    }
}
