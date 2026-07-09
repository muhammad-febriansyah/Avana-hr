<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Records create/update/delete events to `audit_logs` (old/new values, actor,
 * IP, user agent). Sensitive attributes are never written. Do NOT use on the
 * AuditLog model itself.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            $model->writeAuditLog('created', [], $model->auditableValues($model->getAttributes()));
        });

        static::updated(function ($model): void {
            $changes = $model->auditableValues($model->getChanges());
            unset($changes['updated_at']);

            if ($changes === []) {
                return;
            }

            $old = array_intersect_key($model->getOriginal(), $changes);
            $model->writeAuditLog('updated', $model->auditableValues($old), $changes);
        });

        static::deleted(function ($model): void {
            $model->writeAuditLog('deleted', $model->auditableValues($model->getAttributes()), []);
        });
    }

    /**
     * Attributes never recorded in the audit trail (secrets / encrypted PII).
     *
     * @return list<string>
     */
    protected function auditExcluded(): array
    {
        return [
            'password',
            'remember_token',
            'mfa_secret',
            'nik_ktp',
            'nik_ktp_hash',
            'npwp',
            'npwp_hash',
            'bank_account',
            'face_embedding',
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        return array_diff_key($values, array_flip($this->auditExcluded()));
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeAuditLog(string $event, array $old, array $new): void
    {
        AuditLog::create([
            'tenant_id' => $this->tenant_id ?? Auth::user()?->tenant_id,
            'user_id' => Auth::id(),
            'auditable_type' => $this::class,
            'auditable_id' => $this->getKey(),
            'event' => $event,
            'old_values' => $old === [] ? null : $old,
            'new_values' => $new === [] ? null : $new,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 255),
        ]);
    }
}
