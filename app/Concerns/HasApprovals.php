<?php

namespace App\Concerns;

use App\Models\Approval;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * Gives a transaction model a polymorphic link to its approvals.
 * Pair with the App\Contracts\Approvable interface.
 */
trait HasApprovals
{
    /**
     * @return MorphMany<Approval, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function currentApproval(): ?Approval
    {
        return $this->approvals()->latest('id')->first();
    }

    public function isApproved(): bool
    {
        return $this->currentApproval()?->status === 'approved';
    }
}
