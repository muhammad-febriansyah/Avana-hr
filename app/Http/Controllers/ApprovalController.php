<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\ApprovalAction;
use App\Services\Approval\ApprovalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class ApprovalController extends Controller
{
    public function __construct(private ApprovalService $service) {}

    /**
     * "Persetujuan Saya" — requests awaiting the current user at their step.
     */
    public function index(): Response
    {
        $items = ApprovalAction::query()
            ->where('approver_user_id', Auth::id())
            ->where('status', 'pending')
            ->with(['approval' => fn ($query) => $query->with('requester:id,name')])
            ->get()
            ->filter(fn (ApprovalAction $action) => $action->approval !== null
                && $action->approval->status === 'pending'
                && $action->approval->current_step === $action->step_seq)
            ->map(fn (ApprovalAction $action): array => [
                'approval_id' => $action->approval->id,
                'type' => class_basename($action->approval->approvable_type),
                'requester' => $action->approval->requester->name,
                'step' => $action->step_seq,
                'created_at' => $action->approval->created_at?->toIso8601String(),
            ])
            ->values();

        return Inertia::render('approvals/index', [
            'items' => $items,
        ]);
    }

    public function approve(Request $request, Approval $approval): RedirectResponse
    {
        return $this->act($request, $approval, approve: true);
    }

    public function reject(Request $request, Approval $approval): RedirectResponse
    {
        return $this->act($request, $approval, approve: false);
    }

    private function act(Request $request, Approval $approval, bool $approve): RedirectResponse
    {
        $note = $request->string('note')->toString() ?: null;

        try {
            $this->service->act($approval, $request->user(), $approve, $note);
        } catch (RuntimeException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with(
            'success',
            $approve ? 'Pengajuan disetujui.' : 'Pengajuan ditolak.',
        );
    }
}
