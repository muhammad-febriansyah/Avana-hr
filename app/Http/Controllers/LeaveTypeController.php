<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('leave-types/index', [
            'leaveTypes' => LeaveType::orderBy('name')->get()->map(fn (LeaveType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'code' => $type->code,
                'annual_quota' => $type->annual_quota,
                'deduct_balance' => $type->deduct_balance,
                'allow_carry_over' => $type->allow_carry_over,
                'carry_over_max' => $type->carry_over_max,
                'requires_attachment' => $type->requires_attachment,
                'min_notice_days' => $type->min_notice_days,
                'max_consecutive_days' => $type->max_consecutive_days,
            ]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        LeaveType::create($this->validateData($request));

        return back()->with('success', 'Jenis cuti dibuat.');
    }

    public function update(Request $request, LeaveType $leaveType): RedirectResponse
    {
        $leaveType->update($this->validateData($request, $leaveType->id));

        return back()->with('success', 'Jenis cuti diperbarui.');
    }

    public function destroy(LeaveType $leaveType): RedirectResponse
    {
        $leaveType->delete();

        return back()->with('success', 'Jenis cuti dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('leave_types', 'code')
                    ->where('tenant_id', $request->user()->tenant_id)
                    ->whereNull('deleted_at')
                    ->ignore($ignoreId),
            ],
            'annual_quota' => ['required', 'integer', 'min:0', 'max:255'],
            'deduct_balance' => ['boolean'],
            'allow_carry_over' => ['boolean'],
            'carry_over_max' => ['required', 'integer', 'min:0', 'max:255'],
            'requires_attachment' => ['boolean'],
            'min_notice_days' => ['required', 'integer', 'min:0', 'max:255'],
            'max_consecutive_days' => ['nullable', 'integer', 'min:1', 'max:255'],
        ], [
            'name.required' => 'Nama jenis cuti wajib diisi.',
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode sudah digunakan.',
        ]);
    }
}
