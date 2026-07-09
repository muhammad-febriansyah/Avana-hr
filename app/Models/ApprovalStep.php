<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $flow_id
 * @property int $seq
 * @property string $approver_type
 * @property int|null $approver_id
 * @property int|null $min_amount
 * @property int|null $sla_hours
 */
class ApprovalStep extends Model
{
    protected $fillable = [
        'flow_id',
        'seq',
        'approver_type',
        'approver_id',
        'min_amount',
        'sla_hours',
    ];

    protected function casts(): array
    {
        return [
            'seq' => 'integer',
            'min_amount' => 'integer',
            'sla_hours' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ApprovalFlow, $this>
     */
    public function flow(): BelongsTo
    {
        return $this->belongsTo(ApprovalFlow::class, 'flow_id');
    }
}
