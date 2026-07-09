<?php

namespace App\Http\Controllers;

use App\Actions\Approval\SubmitForApproval;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class LeaveRequestController extends Controller
{
    public function __construct(private SubmitForApproval $submit) {}

    public function index(Request $request): Response
    {
        $employeeId = $request->user()->employee_id;
        $year = Carbon::now()->year;

        $month = $request->string('month')->toString();
        $anchor = $month !== '' ? Carbon::parse($month.'-01') : Carbon::now()->startOfMonth();

        return Inertia::render('leave/index', [
            'employeeId' => $employeeId,
            'leaveTypes' => LeaveType::orderBy('name')->get()->map(fn (LeaveType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'requires_attachment' => $type->requires_attachment,
                'min_notice_days' => $type->min_notice_days,
            ]),
            'balances' => $employeeId === null ? [] : LeaveBalance::with('leaveType:id,name')
                ->where('employee_id', $employeeId)
                ->where('year', $year)
                ->get()
                ->map(fn (LeaveBalance $balance): array => [
                    'type' => $balance->leaveType?->name,
                    'entitled' => $balance->entitled,
                    'used' => $balance->used,
                    'pending' => $balance->pending,
                    'expired' => $balance->expired,
                    'available' => $balance->available(),
                ]),
            'requests' => $employeeId === null ? [] : LeaveRequest::with('leaveType:id,name')
                ->where('employee_id', $employeeId)
                ->latest('start_date')
                ->get()
                ->map(fn (LeaveRequest $leave): array => [
                    'id' => $leave->id,
                    'type' => $leave->leaveType?->name,
                    'start_date' => $leave->start_date->toDateString(),
                    'end_date' => $leave->end_date->toDateString(),
                    'total_days' => $leave->total_days,
                    'status' => $leave->status,
                    'can_cancel' => in_array($leave->status, ['pending', 'approved'], true),
                ]),
            'month' => $anchor->format('Y-m'),
            'teamLeaves' => LeaveRequest::query()
                ->with(['employee:id,full_name', 'leaveType:id,name'])
                ->whereIn('status', ['pending', 'approved'])
                ->whereDate('start_date', '<=', $anchor->copy()->endOfMonth()->toDateString())
                ->whereDate('end_date', '>=', $anchor->copy()->startOfMonth()->toDateString())
                ->get()
                ->map(fn (LeaveRequest $leave): array => [
                    'employee' => $leave->employee?->full_name,
                    'type' => $leave->leaveType?->name,
                    'start_date' => $leave->start_date->toDateString(),
                    'end_date' => $leave->end_date->toDateString(),
                    'status' => $leave->status,
                ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $employeeId = $request->user()->employee_id;
        abort_if($employeeId === null, 403, 'Akun tidak terhubung ke data karyawan.');

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'leave_type_id' => ['required', Rule::exists('leave_types', 'id')->whereNull('deleted_at')],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ], [
            'leave_type_id.required' => 'Jenis cuti wajib dipilih.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
        ]);

        $type = LeaveType::findOrFail((int) $data['leave_type_id']);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);

        $this->guardPolicy($type, $start, $request->hasFile('attachment'));

        $totalDays = $this->workingDays($tenantId, $start, $end);
        if ($totalDays <= 0) {
            throw ValidationException::withMessages(['end_date' => 'Rentang tidak mengandung hari kerja (semua libur).']);
        }

        if ($type->max_consecutive_days !== null && $totalDays > $type->max_consecutive_days) {
            throw ValidationException::withMessages(['end_date' => "Melebihi maksimal {$type->max_consecutive_days} hari berturut."]);
        }

        if ($type->deduct_balance) {
            $balance = LeaveBalance::firstOrCreate(
                ['employee_id' => $employeeId, 'leave_type_id' => $type->id, 'year' => $start->year],
                ['tenant_id' => $tenantId, 'entitled' => $type->annual_quota],
            );

            if ($totalDays > $balance->available()) {
                throw ValidationException::withMessages([
                    'leave_type_id' => "Saldo cuti tidak cukup (tersisa {$balance->available()} hari).",
                ]);
            }
        }

        $leave = LeaveRequest::create([
            'employee_id' => $employeeId,
            'leave_type_id' => $type->id,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'total_days' => $totalDays,
            'reason' => $data['reason'] ?? null,
            'attachment_path' => $request->hasFile('attachment')
                ? $request->file('attachment')->store('leave', 'local')
                : null,
            'status' => 'pending',
        ]);

        $leave->reservePending();

        try {
            $this->submit->handle($leave);
        } catch (RuntimeException $exception) {
            $leave->releasePending();
            $leave->delete();

            return back()->with('error', $exception->getMessage());
        }

        return to_route('leave.index')->with('success', 'Pengajuan cuti dibuat.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_if($leaveRequest->employee_id !== $request->user()->employee_id, 403);

        if (! in_array($leaveRequest->status, ['pending', 'approved'], true)) {
            return back()->with('error', 'Pengajuan ini tidak bisa dibatalkan.');
        }

        if ($leaveRequest->status === 'pending') {
            $leaveRequest->releasePending();
            $leaveRequest->approvals()->where('status', 'pending')->update(['status' => 'cancelled']);
        } else {
            $leaveRequest->releaseUsed();
        }

        $leaveRequest->update(['status' => 'cancelled']);

        return back()->with('success', 'Pengajuan cuti dibatalkan, saldo dikembalikan.');
    }

    private function guardPolicy(LeaveType $type, Carbon $start, bool $hasAttachment): void
    {
        if ($type->min_notice_days > 0 && $start->lt(Carbon::today()->addDays($type->min_notice_days))) {
            throw ValidationException::withMessages([
                'start_date' => "Pengajuan minimal {$type->min_notice_days} hari sebelum tanggal mulai.",
            ]);
        }

        if ($type->requires_attachment && ! $hasAttachment) {
            throw ValidationException::withMessages(['attachment' => 'Jenis cuti ini wajib melampirkan dokumen.']);
        }
    }

    /**
     * Inclusive day count in the range excluding holidays (tenant + national).
     */
    private function workingDays(?int $tenantId, Carbon $start, Carbon $end): float
    {
        $holidays = Holiday::visibleTo($tenantId)
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->pluck('date')
            ->mapWithKeys(fn ($date): array => [Carbon::parse($date)->toDateString() => true]);

        $count = 0;
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            if (! isset($holidays[$cursor->toDateString()])) {
                $count++;
            }
            $cursor->addDay();
        }

        return (float) $count;
    }
}
