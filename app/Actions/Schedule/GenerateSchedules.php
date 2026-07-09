<?php

namespace App\Actions\Schedule;

use App\Models\EmployeeSchedule;
use App\Models\Holiday;
use App\Models\ShiftPattern;
use App\Models\ShiftPatternItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Rolls a shift pattern across a date range for a set of employees, writing one
 * `employee_schedules` row per day. National/tenant holidays and approved-leave
 * days become days off, and manually-edited days are preserved (QA-0024).
 */
class GenerateSchedules
{
    /**
     * @param  list<int>  $employeeIds
     * @return int number of schedule days written
     */
    public function handle(ShiftPattern $pattern, array $employeeIds, Carbon $start, Carbon $end): int
    {
        if ($employeeIds === [] || $end->lt($start)) {
            return 0;
        }

        $cycle = max(1, $pattern->cycle_days);
        $itemShift = $pattern->items()->get()
            ->keyBy('day_seq')
            ->map(fn (ShiftPatternItem $item): ?int => $item->shift_id);

        $tenantId = (int) $pattern->tenant_id;

        $holidayDates = Holiday::visibleTo($tenantId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->mapWithKeys(fn ($date): array => [Carbon::parse($date)->toDateString() => true]);

        $leaveByEmployee = $this->approvedLeave($employeeIds, $start, $end);
        $manual = $this->manualDays($employeeIds, $start, $end);

        $now = now();
        $rows = [];

        foreach ($employeeIds as $employeeId) {
            $cursor = $start->copy();

            while ($cursor->lte($end)) {
                $dateStr = $cursor->toDateString();

                if (! isset($manual["{$employeeId}|{$dateStr}"])) {
                    $daySeq = ((int) $start->diffInDays($cursor) % $cycle) + 1;
                    $shiftId = $itemShift->get($daySeq);

                    $isOff = isset($holidayDates[$dateStr])
                        || $this->onLeave($leaveByEmployee[$employeeId] ?? [], $dateStr);

                    $rows[] = [
                        'tenant_id' => $tenantId,
                        'employee_id' => $employeeId,
                        'date' => $dateStr,
                        'shift_id' => $isOff ? null : $shiftId,
                        'is_day_off' => $isOff || $shiftId === null,
                        'source' => 'generated',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }

                $cursor->addDay();
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            EmployeeSchedule::upsert($chunk, ['employee_id', 'date'], ['shift_id', 'is_day_off', 'source', 'updated_at']);
        }

        return count($rows);
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array<int, list<array{0: string, 1: string}>> employee id => list of [start, end] date strings
     */
    private function approvedLeave(array $employeeIds, Carbon $start, Carbon $end): array
    {
        $rows = DB::table('leave_requests')
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get(['employee_id', 'start_date', 'end_date']);

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->employee_id][] = [
                Carbon::parse($row->start_date)->toDateString(),
                Carbon::parse($row->end_date)->toDateString(),
            ];
        }

        return $map;
    }

    /**
     * @param  list<array{0: string, 1: string}>  $intervals
     */
    private function onLeave(array $intervals, string $date): bool
    {
        foreach ($intervals as [$from, $to]) {
            if ($date >= $from && $date <= $to) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<int>  $employeeIds
     * @return array<string, true> "employeeId|date" keys of manually-edited days
     */
    private function manualDays(array $employeeIds, Carbon $start, Carbon $end): array
    {
        $manual = [];

        EmployeeSchedule::query()
            ->whereIn('employee_id', $employeeIds)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->where('source', 'manual')
            ->get(['employee_id', 'date'])
            ->each(function (EmployeeSchedule $schedule) use (&$manual): void {
                $manual["{$schedule->employee_id}|{$schedule->date->toDateString()}"] = true;
            });

        return $manual;
    }
}
