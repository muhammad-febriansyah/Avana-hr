<?php

namespace App\Services\Approval;

use App\Contracts\Approvable;
use App\Models\Approval;
use App\Models\ApprovalAction;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use App\Notifications\ApprovalResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Polymorphic, multi-level approval engine (PRD M00.3).
 *
 * All step actions are created up front from the flow that is active when the
 * request is submitted, so later edits to a flow only affect new requests
 * (QA-0119). A hard rule prevents a requester from approving their own request.
 */
class ApprovalService
{
    /**
     * Start an approval for a transaction. Throws when no active flow exists.
     */
    public function initiate(Model&Approvable $approvable): Approval
    {
        $tenantId = $approvable->getAttribute('tenant_id');
        $type = $approvable->approvalType();

        $flow = ApprovalFlow::query()
            ->withoutGlobalScopes()
            ->with('steps')
            ->where('tenant_id', $tenantId)
            ->where('approvable_type', $type)
            ->where('is_active', true)
            ->first();

        if ($flow === null || $flow->steps->isEmpty()) {
            throw new RuntimeException("Tidak ada alur persetujuan aktif untuk {$type}.");
        }

        return DB::transaction(function () use ($approvable, $flow, $tenantId): Approval {
            $approval = Approval::query()->create([
                'tenant_id' => $tenantId,
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'flow_id' => $flow->id,
                'current_step' => $flow->steps->first()->seq,
                'status' => 'pending',
                'requested_by' => Auth::id(),
            ]);

            // Snapshot every step as an action so flow edits don't reroute this request.
            foreach ($flow->steps as $step) {
                $this->createAction($approval, $step);
            }

            $this->notifyCurrentApprover($approval);

            return $approval;
        });
    }

    /**
     * Record an approve/reject decision by the current approver and advance.
     */
    public function act(Approval $approval, User $actor, bool $approve, ?string $note = null): Approval
    {
        if ((int) $approval->requested_by === (int) $actor->id) {
            throw new RuntimeException('Pemohon tidak boleh menyetujui pengajuannya sendiri.');
        }

        if ($approval->status !== 'pending') {
            throw new RuntimeException('Pengajuan ini sudah selesai diproses.');
        }

        $action = ApprovalAction::query()
            ->where('approval_id', $approval->id)
            ->where('step_seq', $approval->current_step)
            ->where('status', 'pending')
            ->first();

        if ($action === null || (int) $action->approver_user_id !== (int) $actor->id) {
            throw new RuntimeException('Anda bukan penyetuju untuk langkah ini.');
        }

        $action->update([
            'status' => $approve ? 'approved' : 'rejected',
            'note' => $note,
            'acted_at' => now(),
        ]);

        return $approve
            ? $this->advance($approval)
            : $this->finalize($approval, approved: false);
    }

    /**
     * Advance to the next step, or finalize as approved when none remain.
     */
    protected function advance(Approval $approval): Approval
    {
        $next = ApprovalAction::query()
            ->where('approval_id', $approval->id)
            ->where('step_seq', '>', $approval->current_step)
            ->where('status', 'pending')
            ->orderBy('step_seq')
            ->first();

        if ($next === null) {
            return $this->finalize($approval, approved: true);
        }

        $approval->update(['current_step' => $next->step_seq]);
        $this->notifyCurrentApprover($approval);

        return $approval;
    }

    protected function finalize(Approval $approval, bool $approved): Approval
    {
        $approval->update(['status' => $approved ? 'approved' : 'rejected']);

        User::query()->find($approval->requested_by)
            ?->notify(new ApprovalResult($approval, $approved));

        $approvable = $approval->approvable()->first();

        if ($approvable instanceof Approvable) {
            $approved
                ? $approvable->onApprovalApproved()
                : $approvable->onApprovalRejected();
        }

        return $approval;
    }

    /**
     * Escalate a request whose current step has breached its SLA (E2E-0157).
     */
    public function escalate(Approval $approval): bool
    {
        if ($approval->status !== 'pending') {
            return false;
        }

        $action = ApprovalAction::query()
            ->where('approval_id', $approval->id)
            ->where('step_seq', $approval->current_step)
            ->where('status', 'pending')
            ->first();

        if ($action === null) {
            return false;
        }

        $step = ApprovalStep::query()
            ->where('flow_id', $approval->flow_id)
            ->where('seq', $action->step_seq)
            ->first();

        if ($step === null || $step->sla_hours === null) {
            return false;
        }

        if ($action->created_at->addHours($step->sla_hours)->isFuture()) {
            return false;
        }

        $action->update([
            'status' => 'approved',
            'note' => 'Eskalasi otomatis (SLA terlampaui).',
            'acted_at' => now(),
        ]);

        $this->advance($approval);

        return true;
    }

    protected function createAction(Approval $approval, ApprovalStep $step): void
    {
        $resolved = $this->resolveApprover($step, $approval);

        if ($resolved === null) {
            throw new RuntimeException("Penyetuju tidak dapat ditentukan untuk langkah {$step->seq}.");
        }

        [$effective, $delegatedFrom] = $this->applyDelegation($resolved, (int) $approval->tenant_id);

        ApprovalAction::query()->create([
            'approval_id' => $approval->id,
            'step_seq' => $step->seq,
            'approver_user_id' => $effective,
            'delegated_from_user_id' => $delegatedFrom,
            'status' => 'pending',
        ]);
    }

    protected function notifyCurrentApprover(Approval $approval): void
    {
        $action = ApprovalAction::query()
            ->where('approval_id', $approval->id)
            ->where('step_seq', $approval->current_step)
            ->where('status', 'pending')
            ->first();

        if ($action !== null) {
            User::query()->find($action->approver_user_id)
                ?->notify(new ApprovalRequested($approval));
        }
    }

    protected function resolveApprover(ApprovalStep $step, Approval $approval): ?int
    {
        $tenantId = (int) $approval->tenant_id;

        return match ($step->approver_type) {
            'user' => $step->approver_id,
            'role' => User::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereHas('roles', fn ($query) => $query->whereKey($step->approver_id))
                ->value('id'),
            'position' => User::query()->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->whereHas('employee', fn ($query) => $query->where('position_id', $step->approver_id))
                ->value('id'),
            'direct_manager' => $this->resolveDirectManager($approval),
            default => null,
        };
    }

    protected function resolveDirectManager(Approval $approval): ?int
    {
        $employeeId = User::query()->withoutGlobalScopes()
            ->whereKey($approval->requested_by)
            ->value('employee_id');

        if ($employeeId === null) {
            return null;
        }

        $managerEmployeeId = Employee::query()->withoutGlobalScopes()
            ->whereKey($employeeId)
            ->value('direct_manager_employee_id');

        if ($managerEmployeeId === null) {
            return null;
        }

        return User::query()->withoutGlobalScopes()
            ->where('employee_id', $managerEmployeeId)
            ->value('id');
    }

    /**
     * @return array{0: int, 1: int|null} [effectiveApproverId, delegatedFromId]
     */
    protected function applyDelegation(int $userId, int $tenantId): array
    {
        $today = now()->toDateString();

        $delegation = ApprovalDelegation::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('from_user_id', $userId)
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->first();

        return $delegation !== null
            ? [(int) $delegation->to_user_id, $userId]
            : [$userId, null];
    }
}
