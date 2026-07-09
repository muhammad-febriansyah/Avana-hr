<?php

namespace App\Actions\Attendance;

use App\Models\AttendanceEvent;
use App\Models\AttendanceSummary;
use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\Shift;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reconciles one day of attendance for a tenant: for each scheduled employee it
 * derives clock in/out, status (present/late/early_leave/absent/leave/holiday/
 * day_off) and late/work minutes from the raw events, skipping locked days.
 */
class BuildAttendanceSummaries
{
    /**
     * @return int number of summaries written
     */
    public function handle(int $tenantId, Carbon $date): int
    {
        $dateStr = $date->toDateString();

        $schedules = EmployeeSchedule::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $dateStr)
            ->get(['employee_id', 'shift_id', 'is_day_off']);

        if ($schedules->isEmpty()) {
            return 0;
        }

        $shifts = Shift::query()->withoutGlobalScopes()->where('tenant_id', $tenantId)->get()->keyBy('id');
        $isHoliday = Holiday::visibleTo($tenantId)->whereDate('date', $dateStr)->exists();

        $onLeave = DB::table('leave_requests')
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $dateStr)
            ->whereDate('end_date', '>=', $dateStr)
            ->pluck('employee_id')
            ->flip();

        /** @var Collection<int, Collection<int, AttendanceEvent>> $eventsByEmployee */
        $eventsByEmployee = AttendanceEvent::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('occurred_at', $dateStr)
            ->orderBy('occurred_at')
            ->get()
            ->groupBy('employee_id');

        $locked = AttendanceSummary::query()->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereDate('date', $dateStr)
            ->where('is_locked', true)
            ->pluck('employee_id')
            ->flip();

        $written = 0;

        foreach ($schedules as $schedule) {
            $employeeId = (int) $schedule->employee_id;

            if (isset($locked[$employeeId])) {
                continue;
            }

            $events = $eventsByEmployee->get($employeeId) ?? collect();
            $shift = $schedule->shift_id !== null ? $shifts->get($schedule->shift_id) : null;

            $attributes = $this->resolve($date, $schedule, $shift, $events, $isHoliday, isset($onLeave[$employeeId]));

            AttendanceSummary::query()->withoutGlobalScopes()->updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $dateStr],
                ['tenant_id' => $tenantId, 'schedule_shift_id' => $schedule->shift_id, ...$attributes],
            );

            $written++;
        }

        return $written;
    }

    /**
     * @param  Collection<int, AttendanceEvent>  $events
     * @return array{clock_in: ?string, clock_out: ?string, status: string, late_minutes: int, work_minutes: int}
     */
    private function resolve(Carbon $date, EmployeeSchedule $schedule, ?Shift $shift, Collection $events, bool $isHoliday, bool $onLeave): array
    {
        $clockIn = $events->min('occurred_at');
        $clockOut = $events->count() > 1 ? $events->max('occurred_at') : null;

        $base = [
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'late_minutes' => 0,
            'work_minutes' => 0,
        ];

        if ($isHoliday) {
            return [...$base, 'status' => 'holiday'];
        }

        if ($onLeave) {
            return [...$base, 'status' => 'leave'];
        }

        if ($schedule->is_day_off || $shift === null) {
            return [...$base, 'status' => 'day_off'];
        }

        if ($clockIn === null) {
            return [...$base, 'status' => 'absent'];
        }

        $clockInAt = Carbon::parse($clockIn);
        $shiftStart = Carbon::parse($date->toDateString().' '.$shift->start_time);
        $shiftEnd = Carbon::parse($date->toDateString().' '.$shift->end_time);
        $lateThreshold = $shiftStart->copy()->addMinutes($shift->late_tolerance_min);

        $lateMinutes = 0;
        $status = 'present';

        if ($clockInAt->gt($lateThreshold)) {
            $status = 'late';
            $lateMinutes = (int) $shiftStart->diffInMinutes($clockInAt);
        }

        $workMinutes = 0;
        if ($clockOut !== null) {
            $clockOutAt = Carbon::parse($clockOut);
            $workMinutes = max(0, (int) $clockInAt->diffInMinutes($clockOutAt) - $shift->break_minutes);

            if ($status === 'present' && $clockOutAt->lt($shiftEnd)) {
                $status = 'early_leave';
            }
        }

        return [
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'status' => $status,
            'late_minutes' => $lateMinutes,
            'work_minutes' => $workMinutes,
        ];
    }
}
