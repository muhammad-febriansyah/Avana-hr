<?php

namespace App\Console\Commands;

use App\Models\Approval;
use App\Services\Approval\ApprovalService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

class EscalateApprovals extends Command
{
    protected $signature = 'approvals:escalate';

    protected $description = 'Escalate pending approvals whose current step has breached its SLA';

    public function handle(ApprovalService $service): int
    {
        $escalated = 0;

        Approval::query()
            ->withoutGlobalScopes()
            ->where('status', 'pending')
            ->chunkById(100, function (Collection $approvals) use ($service, &$escalated): void {
                foreach ($approvals as $approval) {
                    if ($service->escalate($approval)) {
                        $escalated++;
                    }
                }
            });

        $this->info("Escalated {$escalated} approval(s).");

        return self::SUCCESS;
    }
}
