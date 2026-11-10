<?php

namespace Tests\Feature;

use App\Enums\GroupEvent;
use App\Enums\MessageType;
use App\Events\GroupMessagesReceipts;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private User $carol;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        Storage::fake('public');

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->withPushToken()->create();
        $this->carol = User::factory()->withPushToken()->create();

        // Alice has their numbers
        $this->alice->contacts()->attach([$this->bob->id, $this->carol->id]);

        $this->signIn($this->alice);
    }

    private function signIn(User $user): static
    {
        Sanctum::actingAs($user, ['access']);

        return $this;
    }

    private function createGroup(?array $memberIds = null): Conversation
    {
        $id = $this->signIn($this->alice)->postJson('/api/v1/groups', [
            'name' => 'Famille',
            'member_ids' => $memberIds ?? [$this->bob->id, $this->carol->id],
        ])->assertCreated()->json('id');

        return Conversation::findOrFail($id);
    }

    private function lastNotice(Conversation $group): array
    {
        return $group->messages()->where('message_type', 'system')->latest()->orderByDesc('id')->first()->payload;
    }

    public function test_the_creator_is_admin_and_members_are_notified(): void
    {
        $this->postJson('/api/v1/groups', [
            'name' => 'Famille',
            'description' => 'Les nouvelles',
            'member_ids' => [$this->bob->id, $this->carol->id],
            'avatar' => UploadedFile::fake()->image('photo.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('type', 'group')
            ->assertJsonPath('participant', null)
            ->assertJsonPath('group.name', 'Famille')
            ->assertJsonPath('group.member_count', 3)
            ->assertJsonPath('group.role', 'admin')
            ->assertJsonPath('group.members.0.user.id', $this->alice->id)
            ->assertJsonPath('group.members.0.is_creator', true)
            ->assertJsonPath('can_message', true);

        $group = Conversation::firstWhere('type', 'group');
        $this->assertNotNull($group->avatar_path);
        $this->assertSame(GroupEvent::Created->value, $this->lastNotice($group)['event']);
        Notification::assertSentTo([$this->bob, $this->carol], NewChatMessage::class);
    }

    public function test_only_reachable_people_can_be_added(): void
    {
        $stranger = User::factory()->create();

        $this->postJson('/api/v1/groups', ['name' => 'X', 'member_ids' => [$stranger->id]])
            ->assertJsonValidationErrors('member_ids');
    }

    public function test_a_group_message_reaches_every_member_with_the_group_as_title(): void
    {
        $group = $this->createGroup();
        Notification::fake();

        $this->signIn($this->bob)->postJson("/api/v1/conversations/{$group->id}/messages", [
            'message_type' => 'text',
            'content' => 'Salut',
        ])->assertCreated();

        Notification::assertSentTo($this->carol, NewChatMessage::class, function (NewChatMessage $notification) {
            $push = $notification->toExpo($this->carol);

            return $push->title === 'Famille' && str_ends_with($push->body, ': Salut');
        });
        Notification::assertNotSentTo($this->bob, NewChatMessage::class);
    }

    public function test_only_admins_write_when_the_group_says_so(): void
    {
        $group = $this->createGroup();

        $this->signIn($this->bob)->patchJson("/api/v1/groups/{$group->id}/settings", ['members_can_send' => false])
            ->assertForbidden();

        $this->signIn($this->alice)->patchJson("/api/v1/groups/{$group->id}/settings", ['members_can_send' => false])
            ->assertOk()
            ->assertJsonPath('group.settings.members_can_send', false);
        $this->assertSame('members_can_send', $this->lastNotice($group)['setting']);

        $this->signIn($this->bob)->postJson("/api/v1/conversations/{$group->id}/messages", ['message_type' => 'text', 'content' => 'Hey'])
            ->assertForbidden();
        $this->signIn($this->alice)->postJson("/api/v1/conversations/{$group->id}/messages", ['message_type' => 'text', 'content' => 'Hey'])
            ->assertCreated();
    }

    public function test_members_edit_the_infos_unless_restricted_to_admins(): void
    {
        $group = $this->createGroup();

        $this->signIn($this->bob)->patchJson("/api/v1/groups/{$group->id}", ['name' => 'Cousins'])
            ->assertOk()
            ->assertJsonPath('group.name', 'Cousins');
        $this->assertSame(GroupEvent::Renamed->value, $this->lastNotice($group)['event']);

        $this->signIn($this->alice)->patchJson("/api/v1/groups/{$group->id}/settings", ['members_can_edit_info' => false]);

        $this->signIn($this->bob)->patchJson("/api/v1/groups/{$group->id}", ['name' => 'Autre'])->assertForbidden();
    }

    public function test_members_add_people_unless_restricted_to_admins(): void
    {
        $group = $this->createGroup([$this->bob->id]);
        $this->bob->contacts()->attach($this->carol->id);

        $this->signIn($this->alice)->patchJson("/api/v1/groups/{$group->id}/settings", ['members_can_add' => false]);
        $this->signIn($this->bob)->postJson("/api/v1/groups/{$group->id}/members", ['user_ids' => [$this->carol->id]])
            ->assertForbidden();

        $this->signIn($this->alice)->patchJson("/api/v1/groups/{$group->id}/settings", ['members_can_add' => true]);
        $this->signIn($this->bob)->postJson("/api/v1/groups/{$group->id}/members", ['user_ids' => [$this->carol->id]])
            ->assertOk()
            ->assertJsonPath('group.member_count', 3);

        $notice = $this->lastNotice($group);
        $this->assertSame(GroupEvent::Added->value, $notice['event']);
        $this->assertSame($this->carol->id, $notice['targets'][0]['id']);
    }

    public function test_new_members_do_not_see_the_earlier_history(): void
    {
        $group = $this->createGroup([$this->bob->id]);
        $old = Message::factory()->create(['conversation_id' => $group->id, 'sender_id' => $this->alice->id, 'created_at' => now()->subMinute()]);

        $this->travel(1)->minute();
        $this->signIn($this->alice)->postJson("/api/v1/groups/{$group->id}/members", ['user_ids' => [$this->carol->id]]);

        $ids = collect($this->signIn($this->carol)->getJson("/api/v1/conversations/{$group->id}/messages")->assertOk()->json())->pluck('id');
        $this->assertNotContains($old->id, $ids);
        $this->assertCount(1, $ids); // "Alice added you"
    }

    public function test_a_removed_member_keeps_the_history_but_cannot_write(): void
    {
        $group = $this->createGroup();

        $this->signIn($this->bob)->deleteJson("/api/v1/groups/{$group->id}/members/{$this->carol->id}")->assertForbidden();

        $this->signIn($this->alice)->deleteJson("/api/v1/groups/{$group->id}/members/{$this->carol->id}")
            ->assertOk()
            ->assertJsonPath('group.member_count', 2);
        Notification::assertSentTo($this->carol, NewChatMessage::class);

        $this->travel(1)->minute();
        $later = Message::factory()->create(['conversation_id' => $group->id, 'sender_id' => $this->bob->id]);

        $response = $this->signIn($this->carol)->getJson("/api/v1/conversations/{$group->id}")
            ->assertOk()
            ->assertJsonPath('group.is_member', false)
            ->assertJsonPath('can_message', false);

        $ids = collect($this->getJson("/api/v1/conversations/{$group->id}/messages")->json())->pluck('id');
        $this->assertNotContains($later->id, $ids);
        $this->assertSame(GroupEvent::Removed->value, $this->getJson("/api/v1/conversations/{$group->id}/messages")->json('1.payload.event') ?? $this->lastNotice($group)['event']);

        $this->postJson("/api/v1/conversations/{$group->id}/messages", ['message_type' => 'text', 'content' => 'Hey'])
            ->assertForbidden();
    }

    public function test_the_oldest_member_becomes_admin_when_the_last_admin_leaves(): void
    {
        $group = $this->createGroup();

        $this->signIn($this->alice)->postJson("/api/v1/groups/{$group->id}/leave")
            ->assertOk()
            ->assertJsonPath('group.is_member', false);

        $this->assertSame(1, $group->members()->wherePivot('role', 'admin')->count());
        $this->assertSame(GroupEvent::Promoted->value, $this->lastNotice($group)['event']);
    }

    public function test_a_group_is_deleted_only_after_leaving_it(): void
    {
        $group = $this->createGroup();

        $this->deleteJson("/api/v1/conversations/{$group->id}")->assertUnprocessable();
        $this->postJson("/api/v1/groups/{$group->id}/leave");
        $this->deleteJson("/api/v1/conversations/{$group->id}")->assertNoContent();

        $this->getJson('/api/v1/conversations')->assertJsonCount(0);
    }

    public function test_admins_name_and_remove_admins_but_not_the_creator(): void
    {
        $group = $this->createGroup();

        $this->putJson("/api/v1/groups/{$group->id}/members/{$this->bob->id}/role", ['role' => 'admin'])
            ->assertOk();
        $this->assertTrue($group->fresh()->isAdmin($this->bob));

        $this->signIn($this->bob)->putJson("/api/v1/groups/{$group->id}/members/{$this->alice->id}/role", ['role' => 'member'])
            ->assertForbidden();
        $this->signIn($this->bob)->deleteJson("/api/v1/groups/{$group->id}/members/{$this->alice->id}")
            ->assertForbidden();

        $this->signIn($this->bob)->putJson("/api/v1/groups/{$group->id}/members/{$this->carol->id}/role", ['role' => 'admin'])
            ->assertOk();
        $this->signIn($this->bob)->putJson("/api/v1/groups/{$group->id}/members/{$this->carol->id}/role", ['role' => 'member'])
            ->assertOk();
        $this->assertSame(GroupEvent::Demoted->value, $this->lastNotice($group)['event']);
    }

    public function test_an_admin_deletes_the_message_of_a_member(): void
    {
        $group = $this->createGroup();
        $message = Message::factory()->create(['conversation_id' => $group->id, 'sender_id' => $this->bob->id]);

        $this->signIn($this->carol)->deleteJson("/api/v1/messages/{$message->id}")->assertForbidden();

        $this->signIn($this->alice)->deleteJson("/api/v1/messages/{$message->id}")
            ->assertOk()
            ->assertJsonPath('is_deleted', true)
            ->assertJsonPath('deleted_by_admin.id', $this->alice->id);
    }

    public function test_a_message_is_read_once_every_member_read_it(): void
    {
        $group = $this->createGroup();
        Event::fake([GroupMessagesReceipts::class]);

        $this->travel(1)->second();
        $id = $this->signIn($this->alice)->postJson("/api/v1/conversations/{$group->id}/messages", ['message_type' => 'text', 'content' => 'Hi'])->json('id');
        $this->travel(1)->second();

        $this->signIn($this->bob)->postJson("/api/v1/conversations/{$group->id}/read")->assertNoContent();
        $this->assertFalse(Message::find($id)->is_read);
        $this->assertSame(0, $this->getJson('/api/v1/conversations')->json('0.unread_count'));
        $this->signIn($this->carol);
        $this->assertSame(1, $this->getJson('/api/v1/conversations')->json('0.unread_count'));

        $this->signIn($this->alice)->getJson("/api/v1/messages/{$id}/info")
            ->assertJsonCount(1, 'read')
            ->assertJsonPath('read.0.id', $this->bob->id)
            ->assertJsonPath('pending_count', 1);

        $this->signIn($this->carol)->postJson('/api/v1/me/deliveries')->assertNoContent();
        $this->assertTrue(Message::find($id)->is_delivered);

        $this->postJson("/api/v1/conversations/{$group->id}/read");
        $this->assertTrue(Message::find($id)->is_read);
        Event::assertDispatched(GroupMessagesReceipts::class, fn (GroupMessagesReceipts $event) => in_array($id, $event->readIds, true));
    }

    public function test_joining_with_the_invite_link(): void
    {
        $group = $this->createGroup([$this->bob->id]);
        $dan = User::factory()->create();

        $this->signIn($this->bob)->getJson("/api/v1/groups/{$group->id}/invite")->assertForbidden();
        $code = $this->signIn($this->alice)->getJson("/api/v1/groups/{$group->id}/invite")->assertOk()->json('code');

        $this->signIn($dan)->getJson("/api/v1/group-invites/{$code}")
            ->assertOk()
            ->assertJsonPath('name', 'Famille')
            ->assertJsonPath('is_member', false);
        $this->postJson("/api/v1/group-invites/{$code}/join")->assertOk()->assertJsonPath('status', 'joined');
        $this->assertTrue($group->fresh()->hasMember($dan));
        $this->assertSame(GroupEvent::JoinedViaLink->value, $this->lastNotice($group)['event']);

        $this->signIn($this->alice)->postJson("/api/v1/groups/{$group->id}/invite/reset")->assertOk();
        $this->signIn($dan)->getJson("/api/v1/group-invites/{$code}")->assertNotFound();
    }

    public function test_admins_approve_those_who_join_by_link_when_asked(): void
    {
        $group = $this->createGroup([$this->bob->id]);
        $dan = User::factory()->create();
        $this->patchJson("/api/v1/groups/{$group->id}/settings", ['approval_required' => true]);
        $code = $this->getJson("/api/v1/groups/{$group->id}/invite")->json('code');

        $this->signIn($dan)->postJson("/api/v1/group-invites/{$code}/join")->assertStatus(202)->assertJsonPath('status', 'requested');
        $this->assertFalse($group->fresh()->hasMember($dan));

        $this->signIn($this->alice)->getJson("/api/v1/groups/{$group->id}/join-requests")
            ->assertJsonCount(1)
            ->assertJsonPath('0.user.id', $dan->id);
        $requestId = $group->joinRequests()->value('id');

        $this->postJson("/api/v1/groups/{$group->id}/join-requests/{$requestId}/approve")->assertOk();
        $this->assertTrue($group->fresh()->hasMember($dan));
        $this->assertSame(0, $group->joinRequests()->count());
    }

    public function test_candidates_are_the_people_the_user_can_reach(): void
    {
        $stranger = User::factory()->create();

        $ids = collect($this->getJson('/api/v1/groups/candidates')->assertOk()->json())->pluck('id');

        $this->assertEqualsCanonicalizing([$this->bob->id, $this->carol->id], $ids->all());
        $this->assertNotContains($stranger->id, $ids);
    }

    public function test_the_list_shows_groups_with_the_sender_of_the_last_message(): void
    {
        $group = $this->createGroup();
        $this->travel(1)->second();
        $this->signIn($this->bob)->postJson("/api/v1/conversations/{$group->id}/messages", ['message_type' => 'text', 'content' => 'Coucou']);

        $this->signIn($this->alice)->getJson('/api/v1/conversations')
            ->assertOk()
            ->assertJsonPath('0.type', 'group')
            ->assertJsonPath('0.group.name', 'Famille')
            ->assertJsonPath('0.last_message.sender.username', $this->bob->username)
            ->assertJsonPath('0.unread_count', 1);
    }

    public function test_members_of_a_group_can_write_to_each_other_privately(): void
    {
        $this->createGroup();

        // Bob and Carol do not have each other's number
        $this->signIn($this->bob)->postJson('/api/v1/conversations', ['user_id' => $this->carol->id])
            ->assertCreated();
    }

    public function test_group_calls_are_refused_and_notices_cannot_be_sent_by_users(): void
    {
        $group = $this->createGroup();

        $this->postJson("/api/v1/conversations/{$group->id}/calls", ['type' => 'audio'])->assertUnprocessable();
        $this->postJson("/api/v1/conversations/{$group->id}/messages", ['message_type' => MessageType::System->value])
            ->assertJsonValidationErrors('message_type');
    }

    public function test_former_members_cannot_listen_to_the_group_channel(): void
    {
        $group = $this->createGroup();
        $this->signIn($this->alice)->deleteJson("/api/v1/groups/{$group->id}/members/{$this->carol->id}");

        $this->assertFalse($group->fresh()->hasMember($this->carol));
        $this->assertTrue($group->fresh()->hasParticipant($this->carol));
    }
}
