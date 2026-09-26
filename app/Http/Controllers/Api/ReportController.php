<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreReportRequest;
use App\Http\Resources\ReportResource;
use App\Models\Comment;
use App\Models\Report;
use App\Models\Track;
use App\Models\User;
use App\Notifications\NewReportFiledNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Notification;

class ReportController extends Controller
{
    /**
     * Reported types are mapped through an explicit allowlist rather than
     * trusting the client-supplied class name, so this endpoint can never be
     * used to report an arbitrary model.
     *
     * @var array<string, class-string>
     */
    private const REPORTABLE_TYPES = [
        'track' => Track::class,
        'comment' => Comment::class,
    ];

    public function store(StoreReportRequest $request): JsonResponse
    {
        $modelClass = self::REPORTABLE_TYPES[$request->string('reportable_type')->toString()];
        $reportable = $modelClass::findOrFail($request->integer('reportable_id'));

        $report = Report::create([
            'reporter_id' => $request->user()->id,
            'reportable_type' => $modelClass,
            'reportable_id' => $reportable->id,
            'reason' => $request->string('reason'),
            'details' => $request->input('details'),
            'status' => 'pending',
        ]);

        $this->notifyStaff($report);

        return (new ReportResource($report))->response()->setStatusCode(201);
    }

    private function notifyStaff(Report $report): void
    {
        $staff = User::whereHas('roles', fn ($query) => $query->whereIn('name', ['moderator', 'super-admin']))
            ->get()
            ->filter(fn (User $user) => ($user->notification_preferences ?? $user->defaultNotificationPreferences())['new_reports'] ?? true);

        Notification::send($staff, new NewReportFiledNotification($report));
    }
}
