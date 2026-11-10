<?php

namespace Tests\Feature;

use App\Models\ContactRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\ContactRequestAccepted;
use App\Notifications\ContactRequestReceived;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContactRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->alice = User::factory()->withPushToken()->create(['first_name' => 'Alice', 'last_name' => 'Kaboré', 'phone_number' => '+22670111111']);
        $this->bob = User::factory()->withPushToken()->create(['first_name' => 'Bob', 'last_name' => 'Ouédraogo', 'phone_number' => '+22670222222']);

        Sanctum::actingAs($this->alice, ['access']);
    }

    public function test_a_conversation_with_a_stranger_needs_an_invitation(): void
    {
        $this->postJson('/api/v1/conversations', ['user_id' => $this->bob->id])->assertForbidden();

        $this->postJson('/api/v1/games', [
            'title' => 'Soirée',
            'player_ids' => [$this->bob->id],
            'items' => [['number' => 1, 'content' => 'Un cadeau']],
        ])->assertJsonValidationErrors('player_ids.0');
    }

    public function test_having_the_number_in_either_phone_is_enough(): void
    {
        $this->postJson('/api/v1/contacts/match', ['phone_numbers' => ['70 22 22 22']])->assertJsonCount(1);
        $this->assertTrue($this->alice->contacts()->whereKey($this->bob->id)->exists());

        $this->postJson('/api/v1/conversations', ['user_id' => $this->bob->id])->assertCreated();

        // Bob can answer even without Alice's number in his phone
        $this->assertTrue($this->bob->canReach($this->alice));
    }

    public function test_the_contact_list_mirrors_the_phone_at_each_sync(): void
    {
        $this->postJson('/api/v1/contacts/match', ['phone_numbers' => ['70 22 22 22']]);
        $this->postJson('/api/v1/contacts/match', ['phone_numbers' => ['70 99 99 99']]);

        $this->assertFalse($this->alice->contacts()->exists());
    }

    public function test_search_finds_by_name_or_number_and_hides_the_number_of_strangers(): void
    {
        $this->getJson('/api/v1/users?search=bob ouédraogo')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $this->bob->id)
            ->assertJsonPath('0.phone_number', null)
            ->assertJsonPath('0.relationship.status', 'none')
            ->assertJsonPath('0.relationship.can_send_intro', true);

        $this->getJson('/api/v1/users?search=70 22 22 22')
            ->assertJsonCount(1)
            ->assertJsonPath('0.phone_number', '+22670222222');

        $this->getJson('/api/v1/users?search=70222')->assertJsonCount(0);
    }

    public function test_an_accepted_invitation_lets_them_talk(): void
    {
        $id = $this->postJson('/api/v1/contact-requests', ['user_id' => $this->bob->id, 'message' => 'Salut, on était au lycée ensemble !'])
            ->assertCreated()
            ->assertJsonPath('direction', 'sent')
            ->assertJsonPath('status', 'pending')
            ->json('id');

        Notification::assertSentTo($this->bob, ContactRequestReceived::class);

        $this->getJson('/api/v1/users?search=bob')->assertJsonPath('0.relationship.status', 'request_sent');

        Sanctum::actingAs($this->bob, ['access']);
        $this->getJson('/api/v1/contact-requests')
            ->assertJsonCount(1)
            ->assertJsonPath('0.direction', 'received')
            ->assertJsonPath('0.message', 'Salut, on était au lycée ensemble !')
            ->assertJsonPath('0.user.id', $this->alice->id);

        $this->postJson("/api/v1/contact-requests/{$id}/accept")->assertOk()->assertJsonPath('status', 'accepted');
        Notification::assertSentTo($this->alice, ContactRequestAccepted::class);

        Sanctum::actingAs($this->alice, ['access']);
        $this->postJson('/api/v1/conversations', ['user_id' => $this->bob->id])->assertCreated();
        $this->getJson('/api/v1/users?search=bob')->assertJsonPath('0.relationship.status', 'connected');
    }

    public function test_only_the_invited_user_responds_and_only_the_sender_cancels(): void
    {
        $request = ContactRequest::create(['sender_id' => $this->alice->id, 'recipient_id' => $this->bob->id]);

        $this->postJson("/api/v1/contact-requests/{$request->id}/accept")->assertForbidden();

        Sanctum::actingAs($this->bob, ['access']);
        $this->deleteJson("/api/v1/contact-requests/{$request->id}")->assertForbidden();
    }

    public function test_after_a_decline_a_new_invitation_can_be_sent_but_only_one_presentation_message(): void
    {
        $request = ContactRequest::create(['sender_id' => $this->alice->id, 'recipient_id' => $this->bob->id, 'message' => 'Bonjour']);

        $this->postJson('/api/v1/contact-requests', ['user_id' => $this->bob->id])
            ->assertJsonValidationErrors(['user_id' => 'Vous avez déjà envoyé une invitation à cette personne.']);

        Sanctum::actingAs($this->bob, ['access']);
        $this->postJson("/api/v1/contact-requests/{$request->id}/decline")->assertOk();
        $this->postJson("/api/v1/contact-requests/{$request->id}/accept")->assertJsonValidationErrors('status');

        Sanctum::actingAs($this->alice, ['access']);
        $this->postJson('/api/v1/conversations', ['user_id' => $this->bob->id])->assertForbidden();
        $this->getJson('/api/v1/users?search=bob')
            ->assertJsonPath('0.relationship.status', 'none')
            ->assertJsonPath('0.relationship.can_send_intro', false);

        $this->postJson('/api/v1/contact-requests', ['user_id' => $this->bob->id, 'message' => 'Tu te souviens ?'])
            ->assertJsonValidationErrors('message');
        $this->postJson('/api/v1/contact-requests', ['user_id' => $this->bob->id])->assertCreated();
    }

    public function test_crossed_invitations_are_accepted(): void
    {
        ContactRequest::create(['sender_id' => $this->bob->id, 'recipient_id' => $this->alice->id]);

        $this->postJson('/api/v1/contact-requests', ['user_id' => $this->bob->id])
            ->assertOk()
            ->assertJsonPath('status', 'accepted');

        $this->assertTrue($this->alice->canReach($this->bob));
    }

    public function test_no_invitation_to_someone_already_reachable_or_blocked(): void
    {
        Conversation::factory()->between($this->alice, $this->bob)->create();
        $this->postJson('/api/v1/contact-requests', ['user_id' => $this->bob->id])
            ->assertJsonValidationErrors(['user_id' => 'Vous pouvez déjà discuter avec cette personne.']);

        $carol = User::factory()->create();
        $carol->blockedUsers()->attach($this->alice->id);
        $this->postJson('/api/v1/contact-requests', ['user_id' => $carol->id])->assertJsonValidationErrors('user_id');
    }

    public function test_blocking_cancels_pending_invitations(): void
    {
        ContactRequest::create(['sender_id' => $this->bob->id, 'recipient_id' => $this->alice->id]);

        $this->postJson('/api/v1/blocks', ['user_id' => $this->bob->id])->assertNoContent();

        $this->getJson('/api/v1/contact-requests')->assertJsonCount(0);
    }
}
