<?php

namespace App\Http\Controllers;

use App\Models\ApprovalFlow;
use App\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalFlowController extends Controller
{
    /**
     * Transaction types an approval flow can be attached to.
     *
     * @var list<string>
     */
    private array $approvableTypes = [
        'leave_request',
        'overtime_request',
        'attendance_correction',
        'employee_change_request',
        'employee_movement',
        'payroll_run',
    ];

    public function index(): Response
    {
        $flows = ApprovalFlow::query()
            ->with('steps')
            ->latest()
            ->get()
            ->map(fn (ApprovalFlow $flow): array => [
                'id' => $flow->id,
                'name' => $flow->name,
                'approvable_type' => $flow->approvable_type,
                'is_active' => $flow->is_active,
                'steps' => array_map(fn (ApprovalStep $step): array => [
                    'seq' => $step->seq,
                    'approver_type' => $step->approver_type,
                    'approver_id' => $step->approver_id,
                    'sla_hours' => $step->sla_hours,
                ], $flow->steps->all()),
            ]);

        $users = User::query()
            ->whereNotNull('tenant_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('approval-workflow', [
            'flows' => $flows,
            'users' => $users,
            'approvableTypes' => $this->approvableTypes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'approvable_type' => ['required', Rule::in($this->approvableTypes)],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.approver_type' => ['required', Rule::in(['direct_manager', 'user', 'role', 'position'])],
            'steps.*.approver_id' => ['nullable', 'integer'],
            'steps.*.sla_hours' => ['nullable', 'integer', 'min:1'],
        ], [
            'name.required' => 'Nama alur wajib diisi.',
            'approvable_type.required' => 'Jenis transaksi wajib dipilih.',
            'steps.required' => 'Minimal satu langkah persetujuan.',
        ]);

        DB::transaction(function () use ($validated): void {
            $flow = ApprovalFlow::create([
                'tenant_id' => Auth::user()->tenant_id,
                'approvable_type' => $validated['approvable_type'],
                'name' => $validated['name'],
                'is_active' => true,
            ]);

            foreach (array_values($validated['steps']) as $index => $step) {
                $flow->steps()->create([
                    'seq' => $index + 1,
                    'approver_type' => $step['approver_type'],
                    'approver_id' => $step['approver_id'] ?? null,
                    'sla_hours' => $step['sla_hours'] ?? null,
                ]);
            }
        });

        return back()->with('success', 'Alur persetujuan dibuat.');
    }

    public function destroy(ApprovalFlow $approvalFlow): RedirectResponse
    {
        $approvalFlow->delete();

        return back()->with('success', 'Alur persetujuan dihapus.');
    }
}
