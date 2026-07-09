<?php

use App\Concerns\HasApprovals;
use App\Contracts\Approvable;
use App\Models\ApprovalAction;
use App\Models\ApprovalDelegation;
use App\Models\ApprovalFlow;
use App\Models\ApprovalStep;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Approval\ApprovalService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Minimal Approvable backed by the existing `announcements` table.
 */
class ApprovableStub extends Model implements Approvable
{
    use HasApprovals;

    protected $table = 'announcements';

    protected $guarded = [];

    public function approvalType(): string
    {
        return 'test_stub';
    }

    public function approvalAmount(): ?int
    {
        return null;
    }

    public function onApprovalApproved(): void {}

    public function onApprovalRejected(): void {}
}

function service(): ApprovalService
{
    return app(ApprovalService::class);
}

/**
 * @param  array<int, int>  $approverIds
 * @param  array<int, int|null>  $sla
 */
function makeFlow(int $tenantId, array $approverIds, array $sla = []): ApprovalFlow
{
    $flow = ApprovalFlow::create([
        'tenant_id' => $tenantId,
        'approvable_type' => 'test_stub',
        'name' => 'Test Flow',
        'is_active' => true,
    ]);

    foreach ($approverIds as $index => $userId) {
        ApprovalStep::create([
            'flow_id' => $flow->id,
            'seq' => $index + 1,
            'approver_type' => 'user',
            'approver_id' => $userId,
            'sla_hours' => $sla[$index] ?? null,
        ]);
    }

    return $flow;
}

function stub(int $tenantId): ApprovableStub
{
    return ApprovableStub::create([
        'tenant_id' => $tenantId,
        'title' => 'Pengajuan uji',
    ]);
}

it('QA-0010 routes a multi-level approval in sequence', function () {
    $tenant = Tenant::factory()->create();
    $requester = User::factory()->create(['tenant_id' => $tenant->id]);
    $step1 = User::factory()->create(['tenant_id' => $tenant->id]);
    $step2 = User::factory()->create(['tenant_id' => $tenant->id]);

    makeFlow($tenant->id, [$step1->id, $step2->id]);

    $this->actingAs($requester);
    $approval = service()->initiate(stub($tenant->id));

    expect($approval->status)->toBe('pending');
    expect($approval->current_step)->toBe(1);

    service()->act($approval, $step1, approve: true);
    expect($approval->fresh()->status)->toBe('pending');
    expect($approval->fresh()->current_step)->toBe(2);

    service()->act($approval->fresh(), $step2, approve: true);
    expect($approval->fresh()->status)->toBe('approved');
});

it('rejects when the requester tries to approve their own request', function () {
    $tenant = Tenant::factory()->create();
    $requester = User::factory()->create(['tenant_id' => $tenant->id]);
    $approver = User::factory()->create(['tenant_id' => $tenant->id]);

    makeFlow($tenant->id, [$approver->id]);

    $this->actingAs($requester);
    $approval = service()->initiate(stub($tenant->id));

    expect(fn () => service()->act($approval, $requester, approve: true))
        ->toThrow(RuntimeException::class);
});

it('QA-0011 reassigns a step to an active delegate', function () {
    $tenant = Tenant::factory()->create();
    $requester = User::factory()->create(['tenant_id' => $tenant->id]);
    $approver = User::factory()->create(['tenant_id' => $tenant->id]);
    $delegate = User::factory()->create(['tenant_id' => $tenant->id]);

    ApprovalDelegation::create([
        'tenant_id' => $tenant->id,
        'from_user_id' => $approver->id,
        'to_user_id' => $delegate->id,
        'start_date' => now()->subDay(),
        'end_date' => now()->addDay(),
        'is_active' => true,
    ]);

    makeFlow($tenant->id, [$approver->id]);

    $this->actingAs($requester);
    $approval = service()->initiate(stub($tenant->id));

    $action = ApprovalAction::where('approval_id', $approval->id)->first();
    expect($action->approver_user_id)->toBe($delegate->id);
    expect($action->delegated_from_user_id)->toBe($approver->id);

    // The delegate can act; the original approver can no longer.
    expect(fn () => service()->act($approval, $approver, approve: true))
        ->toThrow(RuntimeException::class);

    service()->act($approval->fresh(), $delegate, approve: true);
    expect($approval->fresh()->status)->toBe('approved');
});

it('QA-0119 applies flow changes only to new requests', function () {
    $tenant = Tenant::factory()->create();
    $requester = User::factory()->create(['tenant_id' => $tenant->id]);
    $approver1 = User::factory()->create(['tenant_id' => $tenant->id]);
    $approver2 = User::factory()->create(['tenant_id' => $tenant->id]);

    $flow = makeFlow($tenant->id, [$approver1->id]);

    $this->actingAs($requester);
    $approvalA = service()->initiate(stub($tenant->id));

    // Add a second step to the flow AFTER approval A was submitted.
    ApprovalStep::create([
        'flow_id' => $flow->id,
        'seq' => 2,
        'approver_type' => 'user',
        'approver_id' => $approver2->id,
    ]);

    $approvalB = service()->initiate(stub($tenant->id));

    expect(ApprovalAction::where('approval_id', $approvalA->id)->count())->toBe(1);
    expect(ApprovalAction::where('approval_id', $approvalB->id)->count())->toBe(2);

    // Approval A finishes in a single step (its original rule).
    service()->act($approvalA, $approver1, approve: true);
    expect($approvalA->fresh()->status)->toBe('approved');
});

it('E2E-0157 escalates a step past its SLA', function () {
    $tenant = Tenant::factory()->create();
    $requester = User::factory()->create(['tenant_id' => $tenant->id]);
    $step1 = User::factory()->create(['tenant_id' => $tenant->id]);
    $step2 = User::factory()->create(['tenant_id' => $tenant->id]);

    makeFlow($tenant->id, [$step1->id, $step2->id], [1, null]);

    $this->actingAs($requester);
    $approval = service()->initiate(stub($tenant->id));

    $this->travel(2)->hours();

    expect(service()->escalate($approval->fresh()))->toBeTrue();
    expect($approval->fresh()->current_step)->toBe(2);

    $this->travelBack();
});
