<?php

namespace App\Http\Controllers\Api\Admin;

use App\Actions\ReviewReport;
use App\Enums\ReportStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\AdminReportResource;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * Moderation queue, restricted to admins by the route middleware.
 */
class ReportController extends Controller
{
    /**
     * Reports, oldest pending first so nothing waits forever.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(ReportStatus::class)],
        ]);

        $status = ReportStatus::tryFrom($validated['status'] ?? '') ?? ReportStatus::Pending;

        $reports = Report::query()
            ->where('status', $status)
            ->with([
                'reportable',
                'reporter',
                'reviewer',
                'reportedUser' => fn ($users) => $users->withCount('reportsReceived'),
            ])
            ->when(
                $status === ReportStatus::Pending,
                fn ($query) => $query->oldest(),
                fn ($query) => $query->latest('reviewed_at'),
            )
            ->orderBy('id')
            ->paginate(20);

        return AdminReportResource::collection($reports);
    }

    public function update(Request $request, Report $report, ReviewReport $reviewReport): AdminReportResource
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in([ReportStatus::Dismissed->value, ReportStatus::Resolved->value])],
            'actions' => ['sometimes', 'array'],
            'actions.*' => [Rule::in([ReviewReport::REMOVE_CONTENT, ReviewReport::SUSPEND_USER])],
            'resolution_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $report = $reviewReport->handle(
            report: $report,
            moderator: $request->user(),
            status: ReportStatus::from($validated['status']),
            actions: $validated['actions'] ?? [],
            note: $validated['resolution_note'] ?? null,
        );

        return AdminReportResource::make($report->load([
            'reportable',
            'reporter',
            'reviewer',
            'reportedUser' => fn ($users) => $users->withCount('reportsReceived'),
        ]));
    }
}
