<?php

namespace App\Actions\Approval;

use App\Contracts\Approvable;
use App\Models\Approval;
use App\Services\Approval\ApprovalService;
use Illuminate\Database\Eloquent\Model;

class SubmitForApproval
{
    public function __construct(private ApprovalService $service) {}

    public function handle(Model&Approvable $approvable): Approval
    {
        return $this->service->initiate($approvable);
    }
}
