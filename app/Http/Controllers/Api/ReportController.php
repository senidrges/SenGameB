<?php

namespace App\Http\Controllers\Api;

use App\Actions\SubmitReport;
use App\Enums\ReportableType;
use App\Enums\ReportReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReportRequest;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    /**
     * Reasons a user can pick when reporting.
     */
    public function reasons(): JsonResponse
    {
        return response()->json(array_map(
            fn (ReportReason $reason) => ['value' => $reason->value, 'label' => $reason->label()],
            ReportReason::cases(),
        ));
    }

    public function store(StoreReportRequest $request, SubmitReport $submitReport): JsonResponse
    {
        $reporter = $request->user();

        $report = $submitReport->handle(
            reporter: $reporter,
            type: ReportableType::from($request->validated('type')),
            targetId: $request->validated('id'),
            reason: ReportReason::from($request->validated('reason')),
            details: $request->validated('details'),
        );

        if ($request->boolean('also_block') && $report->reportedUser !== null) {
            $reporter->blockedUsers()->syncWithoutDetaching([$report->reported_user_id => ['created_at' => now()]]);
        }

        return response()->json([
            'id' => $report->id,
            'status' => $report->status,
            'message' => 'Merci. Notre équipe de modération va examiner ce signalement.',
        ], $report->wasRecentlyCreated ? 201 : 200);
    }
}
