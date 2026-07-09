<?php

namespace App\Contracts;

/**
 * A transaction that can be routed through the approval engine.
 * Implementing models should also use App\Concerns\HasApprovals.
 */
interface Approvable
{
    /**
     * Flow key that maps to approval_flows.approvable_type
     * (e.g. 'leave_request', 'overtime_request', 'payroll_run').
     */
    public function approvalType(): string;

    /**
     * Nominal amount used to match step conditions (min_amount), or null.
     */
    public function approvalAmount(): ?int;

    /**
     * Side effects when the approval is fully approved.
     */
    public function onApprovalApproved(): void;

    /**
     * Side effects when the approval is rejected.
     */
    public function onApprovalRejected(): void;
}
