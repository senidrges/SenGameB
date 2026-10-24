<?php

namespace App\Http\Resources;

use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A report as seen by moderators.
 *
 * @mixin Report
 */
class AdminReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'target_type' => $this->reportable_type,
            'target_id' => $this->reportable_id,
            // False once the content was deleted (by its author or a moderator).
            'target_exists' => $this->reportable !== null,
            'reason' => $this->reason,
            'reason_label' => $this->reason->label(),
            'details' => $this->details,
            'content_snapshot' => $this->content_snapshot,
            'status' => $this->status,
            'resolution_note' => $this->resolution_note,
            'created_at' => $this->created_at,
            'reviewed_at' => $this->reviewed_at,
            'reporter' => $this->whenLoaded('reporter', fn () => $this->reporter === null ? null : [
                'id' => $this->reporter->id,
                'username' => $this->reporter->username,
            ]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer === null ? null : [
                'id' => $this->reviewer->id,
                'username' => $this->reviewer->username,
            ]),
            'reported_user' => $this->whenLoaded('reportedUser', fn () => $this->reportedUser === null ? null : [
                'id' => $this->reportedUser->id,
                'username' => $this->reportedUser->username,
                'avatar_url' => $this->reportedUser->avatar_url,
                'is_suspended' => $this->reportedUser->isSuspended(),
                'suspension_reason' => $this->reportedUser->suspension_reason,
                'reports_count' => (int) ($this->reportedUser->reports_received_count ?? 0),
            ]),
        ];
    }
}
