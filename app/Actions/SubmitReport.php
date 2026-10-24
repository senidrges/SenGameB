<?php

namespace App\Actions;

use App\Enums\ReportableType;
use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class SubmitReport
{
    /**
     * Report an account or a piece of content to the moderators.
     *
     * Users can only report what they can see. A copy of the content is kept
     * so moderators can review it even if it is edited or deleted later.
     * Reporting the same thing twice keeps the first pending report.
     */
    public function handle(User $reporter, ReportableType $type, string $targetId, ReportReason $reason, ?string $details): Report
    {
        $target = $this->findVisibleTarget($reporter, $type, $targetId);
        $reportedUser = $this->authorOf($target);

        if ($reportedUser?->is($reporter)) {
            throw ValidationException::withMessages([
                'id' => 'Vous ne pouvez pas signaler votre propre compte ou contenu.',
            ]);
        }

        $pending = Report::query()
            ->whereMorphedTo('reportable', $target)
            ->where('reporter_id', $reporter->id)
            ->where('status', ReportStatus::Pending)
            ->first();

        if ($pending !== null) {
            return $pending;
        }

        $report = new Report([
            'reporter_id' => $reporter->id,
            'reported_user_id' => $reportedUser?->id,
            'reason' => $reason,
            'details' => $details,
            'content_snapshot' => $this->snapshotOf($target),
        ]);
        $report->reportable()->associate($target);
        $report->save();

        return $report;
    }

    /**
     * The reported model, as long as the reporter is allowed to see it.
     * Anything else is "not found" so reports cannot be used to probe ids.
     */
    private function findVisibleTarget(User $reporter, ReportableType $type, string $targetId): Model
    {
        $target = $type->modelClass()::query()->find($targetId);

        $isVisible = match (true) {
            $target instanceof User => $target->hasProfile(),
            $target instanceof Message => $target->conversation->hasParticipant($reporter),
            $target instanceof GameMessage => $target->game->hasParticipant($reporter),
            $target instanceof LoveGame => $target->hasParticipant($reporter),
            default => false,
        };

        if (! $isVisible) {
            throw (new ModelNotFoundException)->setModel($type->modelClass(), [$targetId]);
        }

        return $target;
    }

    private function authorOf(Model $target): ?User
    {
        return match (true) {
            $target instanceof User => $target,
            $target instanceof Message, $target instanceof GameMessage => $target->sender,
            $target instanceof LoveGame => $target->organizer,
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotOf(Model $target): array
    {
        return match (true) {
            $target instanceof User => [
                'username' => $target->username,
                'first_name' => $target->first_name,
                'last_name' => $target->last_name,
                'avatar_url' => $target->avatar_url,
                'status' => $target->status,
            ],
            $target instanceof Message => [
                'content' => $target->content,
                'message_type' => $target->message_type->value,
                'file_url' => $target->file_url,
                'file_name' => $target->file_name,
                'sender_username' => $target->sender?->username,
                'conversation_id' => $target->conversation_id,
                'sent_at' => $target->created_at?->toIso8601String(),
            ],
            $target instanceof GameMessage => [
                'content' => $target->content,
                'sender_username' => $target->sender?->username,
                'game_id' => $target->game_id,
                'game_title' => $target->game->title,
                'sent_at' => $target->created_at?->toIso8601String(),
            ],
            $target instanceof LoveGame => [
                'title' => $target->title,
                'organizer_username' => $target->organizer?->username,
                'items' => $target->items->map(fn ($item) => ['number' => $item->number, 'content' => $item->content])->all(),
            ],
            default => [],
        };
    }
}
