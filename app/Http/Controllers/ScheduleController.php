<?php

namespace App\Http\Controllers;

use App\Actions\Schedule\GenerateSchedules;
use App\Models\Employee;
use App\Models\EmployeeSchedule;
use App\Models\ShiftPattern;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScheduleController extends Controller
{
    public function index(Request $request): Response
    {
        $month = $request->string('month')->toString();
        $anchor = $month !== '' ? Carbon::parse($month.'-01') : Carbon::now()->startOfMonth();
        $start = $anchor->copy()->startOfMonth();
        $end = $anchor->copy()->endOfMonth();

        $schedules = EmployeeSchedule::query()
            ->with('shift:id,name')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->map(fn (EmployeeSchedule $schedule): array => [
                'employee_id' => $schedule->employee_id,
                'date' => $schedule->date->toDateString(),
                'shift' => $schedule->shift?->name,
                'is_day_off' => $schedule->is_day_off,
            ]);

        return Inertia::render('schedules/index', [
            'month' => $start->format('Y-m'),
            'daysInMonth' => $start->daysInMonth,
            'schedules' => $schedules,
            'patterns' => ShiftPattern::orderBy('name')->get(['id', 'name', 'cycle_days']),
            'employees' => Employee::where('status', 'active')->orderBy('full_name')->get(['id', 'full_name']),
        ]);
    }

    public function generate(Request $request, GenerateSchedules $generator): RedirectResponse
    {
        $data = $request->validate([
            'pattern_id' => ['required', Rule::exists('shift_patterns', 'id')],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => [Rule::exists('employees', 'id')->whereNull('deleted_at')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date', 'before_or_equal:'.Carbon::parse($request->input('start_date', 'now'))->addYear()->toDateString()],
        ], [
            'pattern_id.required' => 'Pola shift wajib dipilih.',
            'employee_ids.required' => 'Pilih minimal satu karyawan.',
            'end_date.before_or_equal' => 'Rentang tanggal maksimal 1 tahun.',
        ]);

        /** @var ShiftPattern $pattern */
        $pattern = ShiftPattern::findOrFail($data['pattern_id']);

        $count = $generator->handle(
            $pattern,
            array_values(array_map('intval', $data['employee_ids'])),
            Carbon::parse($data['start_date']),
            Carbon::parse($data['end_date']),
        );

        return back()->with('success', "{$count} jadwal ter-generate.");
    }
}
