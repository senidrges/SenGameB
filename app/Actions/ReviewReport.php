<?php

namespace App\Actions;

use App\Enums\ReportStatus;
use App\Events\GameChanged;
use App\Events\GameMessageChanged;
use App\Events\MessageUpdated;
use App\Models\GameMessage;
use App\Models\LoveGame;
use App\Models\Message;
use App\Models\Report;
use App\Models\User;
use App\Support\Realtime;
use Illuminate\Support\Facades\DB;

class ReviewReport
{
    public const REMOVE_CONTENT = 'remove_content';

    public const SUSPEND_USER = 'suspend_user';

    public function __construct(private SuspendAccount $suspendAccount) {}

    /**
     * Close a report and apply the moderator's decision.
     *
     * Every other pending report about the same account or content gets the
     * same decision, so moderators never review the same thing twice.
     *
     * @param  list<string>  $actions  REMOVE_CONTENT and/or SUSPEND_USER
     */
    public function handle(Report $report, User $moderator, ReportStatus $status, array $actions = [], ?string $note = null): Report
    {
        $target = $report->reportable;

        DB::transaction(function () use ($report, $moderator, $status, $note): void {
            Report::query()
                ->where('reportable_type', $report->reportable_type)
                ->where('reportable_id', $report->reportable_id)
                ->where(fn ($reports) => $reports->where('status', ReportStatus::Pending)->orWhereKey($report->id))
                ->update([
                    'status' => $status,
                    'reviewed_by' => $moderator->id,
                    'reviewed_at' => now(),
                    'resolution_note' => $note,
                ]);
        });

        if (in_array(self::REMOVE_CONTENT, $actions, true) && $target !== null) {
            $this->removeContent($target);
        }

        if (in_array(self::SUSPEND_USER, $actions, true) && $report->reportedUser !== null && ! $report->reportedUser->is_admin) {
            $this->suspendAccount->suspend(
                $report->reportedUser,
                $note ?? $report->reason->label(),
            );
        }

        return $report->refresh();
    }

    private function removeContent(object $target): void
    {
        if ($target instanceof Message) {
            $target->markDeletedForEveryone();
            Realtime::send(new MessageUpdated($target));
        } elseif ($target instanceof GameMessage) {
            $target->markDeletedForEveryone();
            Realtime::send(new GameMessageChanged($target));
        } elseif ($target instanceof LoveGame) {
            $participantIds = $target->participantIds();
            $target->delete();
            Realtime::send(GameChanged::deleted($target, $participantIds));
        }
    }
}
