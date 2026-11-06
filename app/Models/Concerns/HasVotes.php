<?php

namespace App\Models\Concerns;

use App\Enums\MessageType;
use Illuminate\Support\Facades\DB;

/**
 * Answers to a poll or an event, stored as a JSON map of option id => list
 * of user ids. The model has `message_type`, `payload` and `votes`.
 */
trait HasVotes
{
    /**
     * Ids of the answers a poll or an event offers.
     *
     * @return list<string>
     */
    public function voteOptions(): array
    {
        return match ($this->message_type) {
            MessageType::Poll => array_column($this->payload['options'] ?? [], 'id'),
            MessageType::Event => ['going', 'maybe', 'not_going'],
            default => [],
        };
    }

    /**
     * Replace the user's answers to a poll or an event (none to withdraw).
     *
     * The row is locked so two users voting at the same time never
     * overwrite each other.
     *
     * @param  list<string>  $optionIds
     */
    public function setVotes(string $userId, array $optionIds): void
    {
        DB::transaction(function () use ($userId, $optionIds): void {
            $locked = static::query()->whereKey($this->getKey())->lockForUpdate()->firstOrFail();

            $votes = [];
            foreach ($this->voteOptions() as $option) {
                $users = array_values(array_diff($locked->votes[$option] ?? [], [$userId]));
                if (in_array($option, $optionIds, true)) {
                    $users[] = $userId;
                }
                if ($users !== []) {
                    $votes[$option] = $users;
                }
            }

            $this->forceFill(['votes' => $votes === [] ? null : $votes])->save();
        });
    }
}
