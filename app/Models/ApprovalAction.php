<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $approval_id
 * @property int $step_seq
 * @property int $approver_user_id
 * @property int|null $delegated_from_user_id
 * @property string $status
 * @property string|null $note
 * @property Carbon|null $acted_at
 */
class ApprovalAction extends Model
{
    protected $fillable = [
        'approval_id',
        'step_seq',
        'approver_user_id',
        'delegated_from_user_id',
        'status',
        'note',
        'acted_at',
    ];

    protected function casts(): array
    {
        return [
            'step_seq' => 'integer',
            'acted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Approval, $this>
     */
    public function approval(): BelongsTo
    {
        return $this->belongsTo(Approval::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }
}
