<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminTrackResource;
use App\Http\Resources\ReportResource;
use App\Models\Comment;
use App\Models\Report;
use App\Models\Track;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminModerationController extends Controller
{
    public function tracks(Request $request): AnonymousResourceCollection
    {
        $query = Track::query()->with('artist')->withCount('reports')->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        if ($type = $request->string('type')->toString()) {
            $query->whereIn('type', explode(',', $type));
        }

        if ($search = $request->string('q')->toString()) {
            $query->where('title', 'like', "%{$search}%");
        }

        return AdminTrackResource::collection($query->paginate(20));
    }

    public function approveTrack(Request $request, Track $track): JsonResponse
    {
        $track->forceFill([
            'status' => 'approved',
            'rejection_reason' => null,
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ])->save();

        return response()->json(['track' => new AdminTrackResource($track)]);
    }

    public function rejectTrack(Request $request, Track $track): JsonResponse
    {
        $request->validate(['reason' => ['required', 'string', 'max:500']]);

        $track->forceFill([
            'status' => 'rejected',
            'rejection_reason' => $request->string('reason'),
            'moderated_by' => $request->user()->id,
            'moderated_at' => now(),
        ])->save();

        return response()->json(['track' => new AdminTrackResource($track)]);
    }

    public function reports(Request $request): AnonymousResourceCollection
    {
        $query = Report::query()->with(['reporter', 'reportable', 'resolver'])->latest();

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        return ReportResource::collection($query->paginate(20));
    }

    public function resolveReport(Request $request, Report $report): JsonResponse
    {
        $request->validate([
            'action' => ['required', 'string', 'in:dismiss,action'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $action = $request->string('action')->toString();

        if ($action === 'action') {
            $this->actionReportedContent($request, $report);
        }

        $report->forceFill([
            'status' => $action === 'action' ? 'actioned' : 'dismissed',
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
            'resolution_note' => $request->input('note'),
        ])->save();

        return response()->json(['report' => new ReportResource($report->fresh(['reporter', 'reportable', 'resolver']))]);
    }

    /**
     * Taking action on a report removes the offending content: a reported
     * track is rejected (same as a direct moderator rejection), a reported
     * comment is deleted outright since there's no "hidden comment" state.
     */
    private function actionReportedContent(Request $request, Report $report): void
    {
        $reportable = $report->reportable;

        if (! $reportable) {
            return;
        }

        if ($reportable instanceof Track) {
            $reportable->forceFill([
                'status' => 'rejected',
                'rejection_reason' => 'Reported: '.$report->reason,
                'moderated_by' => $request->user()->id,
                'moderated_at' => now(),
            ])->save();
        } elseif ($reportable instanceof Comment) {
            $reportable->delete();
        }
    }
}
