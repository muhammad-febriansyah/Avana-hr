<?php

namespace App\Actions\Approval;

use App\Models\Approval;
use App\Models\User;
use App\Services\Approval\ApprovalService;

class ActOnApproval
{
    public function __construct(private ApprovalService $service) {}

    public function handle(Approval $approval, User $actor, bool $approve, ?string $note = null): Approval
    {
        return $this->service->act($approval, $actor, $approve, $note);
    }
}
