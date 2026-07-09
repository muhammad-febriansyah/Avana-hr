<?php

namespace App\Http\Controllers;

use App\Actions\Attendance\BuildAttendanceSummaries;
use App\Models\AttendanceSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function index(Request $request): Response
    {
        $date = $request->string('date')->toString();
        $date = $date !== '' ? $date : Carbon::today()->toDateString();

        $summaries = AttendanceSummary::query()
            ->with(['employee:id,full_name', 'shift:id,name'])
            ->whereDate('date', $date)
            ->get();

        return Inertia::render('attendance/index', [
            'date' => $date,
            'summaries' => $summaries->map(fn (AttendanceSummary $summary): array => [
                'id' => $summary->id,
                'employee' => $summary->employee?->full_name,
                'shift' => $summary->shift?->name,
                'clock_in' => $summary->clock_in?->format('H:i'),
                'clock_out' => $summary->clock_out?->format('H:i'),
                'status' => $summary->status,
                'late_minutes' => $summary->late_minutes,
                'work_minutes' => $summary->work_minutes,
                'is_locked' => $summary->is_locked,
            ]),
            'counts' => $summaries->groupBy('status')->map(fn ($group): int => $group->count()),
        ]);
    }

    /**
     * Manually (re)build the daily summaries from events — the scheduled
     * `attendance:rebuild` command does this nightly once face-recognition
     * events are flowing in from the mobile app.
     */
    public function rebuild(Request $request, BuildAttendanceSummaries $builder): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $count = $builder->handle((int) $request->user()->tenant_id, Carbon::parse($data['date']));

        return back()->with('success', "{$count} ringkasan kehadiran disusun.");
    }
}
