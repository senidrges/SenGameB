<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewChatMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SharedMessageTest extends TestCase
{
    use RefreshDatabase;

    private User $alice;

    private User $bob;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->alice = User::factory()->create();
        $this->bob = User::factory()->withPushToken()->create();
        $this->conversation = Conversation::factory()->between($this->alice, $this->bob)->create();

        Sanctum::actingAs($this->alice, ['access']);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function send(string $type, array $payload): TestResponse
    {
        return $this->postJson("/api/v1/conversations/{$this->conversation->id}/messages", [
            'message_type' => $type,
            'payload' => $payload,
        ]);
    }

    private function poll(bool $allowMultiple = false): Message
    {
        $id = $this->send('poll', [
            'question' => 'On mange où ?',
            'options' => ['Maquis', 'Pizzeria', 'À la maison'],
            'allow_multiple' => $allowMultiple,
        ])->assertCreated()->json('id');

        return Message::findOrFail($id);
    }

    public function test_a_location_is_shared(): void
    {
        $this->send('location', ['latitude' => 12.3714, 'longitude' => -1.5197, 'address' => 'Ouagadougou', 'extra' => 'ignoré'])
            ->assertCreated()
            ->assertJsonPath('message_type', 'location')
            ->assertJsonPath('content', null)
            ->assertJsonPath('payload', ['latitude' => 12.3714, 'longitude' => -1.5197, 'name' => null, 'address' => 'Ouagadougou']);

        Notification::assertSentTo($this->bob, NewChatMessage::class,
            fn (NewChatMessage $notification) => $notification->toExpo($this->bob)->body === '📍 Localisation');
    }

    public function test_a_location_out_of_range_is_refused(): void
    {
        $this->send('location', ['latitude' => 120, 'longitude' => 0])->assertJsonValidationErrors('payload.latitude');
    }

    public function test_a_contact_is_shared(): void
    {
        $this->send('contact', ['name' => 'Awa', 'phone_numbers' => ['+22670000000']])
            ->assertCreated()
            ->assertJsonPath('payload', ['name' => 'Awa', 'phone_numbers' => ['+22670000000']]);
    }

    public function test_a_poll_needs_two_distinct_options(): void
    {
        $this->send('poll', ['question' => 'Oui ?', 'options' => ['Oui']])->assertJsonValidationErrors('payload.options');
        $this->send('poll', ['question' => 'Oui ?', 'options' => ['Oui', 'oui']])->assertJsonValidationErrors('payload.options.1');
    }

    public function test_poll_options_get_an_id(): void
    {
        $poll = $this->poll();

        $this->assertSame([
            ['id' => 'o1', 'text' => 'Maquis'],
            ['id' => 'o2', 'text' => 'Pizzeria'],
            ['id' => 'o3', 'text' => 'À la maison'],
        ], $poll->payload['options']);
        $this->assertFalse($poll->payload['allow_multiple']);
    }

    public function test_voting_replaces_the_previous_answer(): void
    {
        $poll = $this->poll();

        $this->postJson("/api/v1/messages/{$poll->id}/votes", ['option_ids' => ['o1']])
            ->assertOk()
            ->assertJsonPath('votes', ['o1' => [$this->alice->id]]);

        Sanctum::actingAs($this->bob, ['access']);
        $this->postJson("/api/v1/messages/{$poll->id}/votes", ['option_ids' => ['o1']])->assertOk();

        Sanctum::actingAs($this->alice, ['access']);
        $this->postJson("/api/v1/messages/{$poll->id}/votes", ['option_ids' => ['o2']])
            ->assertOk()
            ->assertJsonPath('votes', ['o1' => [$this->bob->id], 'o2' => [$this->alice->id]]);

        $this->postJson("/api/v1/messages/{$poll->id}/votes", ['option_ids' => []])
            ->assertOk()
            ->assertJsonPath('votes', ['o1' => [$this->bob->id]]);
    }

    public function test_several_answers_need_a_multiple_choice_poll(): void
    {
        $this->postJson("/api/v1/messages/{$this->poll()->id}/votes", ['option_ids' => ['o1', 'o2']])
            ->assertJsonValidationErrors('option_ids');

        $this->postJson("/api/v1/messages/{$this->poll(allowMultiple: true)->id}/votes", ['option_ids' => ['o1', 'o2']])
            ->assertOk()
            ->assertJsonPath('votes', ['o1' => [$this->alice->id], 'o2' => [$this->alice->id]]);
    }

    public function test_unknown_options_are_refused(): void
    {
        $this->postJson("/api/v1/messages/{$this->poll()->id}/votes", ['option_ids' => ['o9']])
            ->assertJsonValidationErrors('option_ids.0');
    }

    public function test_an_event_is_shared_and_answered(): void
    {
        $id = $this->send('event', [
            'title' => 'Anniversaire',
            'starts_at' => '2026-11-14T18:00:00+00:00',
            'ends_at' => '2026-11-14T23:00:00+00:00',
            'location' => 'Chez Awa',
        ])
            ->assertCreated()
            ->assertJsonPath('payload.title', 'Anniversaire')
            ->assertJsonPath('payload.starts_at', '2026-11-14T18:00:00+00:00')
            ->json('id');

        $this->postJson("/api/v1/messages/{$id}/votes", ['option_ids' => ['going']])
            ->assertOk()
            ->assertJsonPath('votes', ['going' => [$this->alice->id]]);
    }

    public function test_an_event_cannot_end_before_it_starts(): void
    {
        $this->send('event', [
            'title' => 'Anniversaire',
            'starts_at' => '2026-11-14T18:00:00+00:00',
            'ends_at' => '2026-11-14T17:00:00+00:00',
        ])->assertJsonValidationErrors('payload.ends_at');
    }

    public function test_text_messages_cannot_be_voted_on(): void
    {
        $message = Message::factory()->create(['conversation_id' => $this->conversation->id]);

        $this->postJson("/api/v1/messages/{$message->id}/votes", ['option_ids' => []])->assertUnprocessable();
    }

    public function test_outsiders_cannot_vote(): void
    {
        $poll = $this->poll();

        Sanctum::actingAs(User::factory()->create(), ['access']);

        $this->postJson("/api/v1/messages/{$poll->id}/votes", ['option_ids' => ['o1']])->assertForbidden();
    }

    public function test_forwarding_a_poll_keeps_the_question_but_not_the_votes(): void
    {
        $poll = $this->poll();
        $poll->setVotes($this->bob->id, ['o1']);
        $target = Conversation::factory()->between($this->alice, User::factory()->create())->create();

        $this->postJson("/api/v1/messages/{$poll->id}/forward", ['conversation_id' => $target->id])
            ->assertCreated()
            ->assertJsonPath('payload.question', 'On mange où ?')
            ->assertJsonPath('votes', null);
    }

    public function test_deleting_a_poll_removes_its_data(): void
    {
        $poll = $this->poll();
        $poll->setVotes($this->bob->id, ['o1']);

        $this->deleteJson("/api/v1/messages/{$poll->id}")
            ->assertOk()
            ->assertJsonPath('payload', null)
            ->assertJsonPath('votes', null);
    }
}
