<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ModerationTest extends TestCase
{
    use RefreshDatabase;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->moderator = User::factory()->admin()->create();
    }

    public function test_only_admins_see_the_moderation_queue(): void
    {
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson('/api/v1/admin/reports')->assertForbidden();
    }

    public function test_the_queue_lists_pending_reports_with_the_reported_account(): void
    {
        $reported = User::factory()->create();
        Report::factory()->count(2)->about($reported, $reported)->create();
        Report::factory()->create(['status' => 'dismissed']);
        Sanctum::actingAs($this->moderator, ['access']);

        $this->getJson('/api/v1/admin/reports')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.reason_label', 'Harcèlement ou intimidation')
            ->assertJsonPath('data.0.reported_user.id', $reported->id)
            ->assertJsonPath('data.0.reported_user.reports_count', 2);
    }

    public function test_dismissing_a_report_closes_every_pending_report_about_the_same_target(): void
    {
        $reported = User::factory()->create();
        [$first] = Report::factory()->count(2)->about($reported, $reported)->create();
        Sanctum::actingAs($this->moderator, ['access']);

        $this->patchJson("/api/v1/admin/reports/{$first->id}", ['status' => 'dismissed', 'resolution_note' => 'RAS'])
            ->assertOk()
            ->assertJsonPath('status', 'dismissed')
            ->assertJsonPath('reviewer.id', $this->moderator->id);

        $this->assertSame(0, Report::query()->where('status', 'pending')->count());
    }

    public function test_resolving_can_remove_the_content_and_suspend_its_author(): void
    {
        $author = User::factory()->create();
        $author->createToken('device', ['access']);
        $conversation = Conversation::factory()->between($author, User::factory()->create())->create();
        $message = Message::factory()->create(['conversation_id' => $conversation->id, 'sender_id' => $author->id]);
        $report = Report::factory()->about($message, $author)->create();
        Sanctum::actingAs($this->moderator, ['access']);

        $this->patchJson("/api/v1/admin/reports/{$report->id}", [
            'status' => 'resolved',
            'actions' => ['remove_content', 'suspend_user'],
            'resolution_note' => 'Harcèlement répété',
        ])
            ->assertOk()
            ->assertJsonPath('reported_user.is_suspended', true);

        $this->assertTrue($message->fresh()->is_deleted);
        $this->assertSame('Harcèlement répété', $author->fresh()->suspension_reason);
        $this->assertSame(0, $author->tokens()->count());
    }

    public function test_a_suspended_account_is_refused_everywhere(): void
    {
        $user = User::factory()->create(['email' => 'banned@example.com', 'suspended_at' => now()]);

        $this->postJson('/api/v1/auth/login', ['email' => 'banned@example.com', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('code', 'account_suspended');

        Sanctum::actingAs($user, ['access']);
        $this->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'account_suspended');
    }

    public function test_suspended_accounts_are_hidden_from_search(): void
    {
        User::factory()->create(['username' => 'banned_user', 'suspended_at' => now()]);
        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->getJson('/api/v1/users?search=banned')->assertJsonCount(0);
    }

    public function test_admins_suspend_and_reinstate_accounts(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($this->moderator, ['access']);

        $this->postJson("/api/v1/admin/users/{$user->id}/suspension", ['reason' => 'Spam'])->assertNoContent();
        $this->assertTrue($user->fresh()->isSuspended());

        $this->deleteJson("/api/v1/admin/users/{$user->id}/suspension")->assertNoContent();
        $this->assertFalse($user->fresh()->isSuspended());
    }

    public function test_admins_cannot_be_suspended(): void
    {
        $otherAdmin = User::factory()->admin()->create();
        Sanctum::actingAs($this->moderator, ['access']);

        $this->postJson("/api/v1/admin/users/{$otherAdmin->id}/suspension", ['reason' => 'Test'])->assertUnprocessable();
    }
}
