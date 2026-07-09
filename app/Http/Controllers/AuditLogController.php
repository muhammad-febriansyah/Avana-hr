<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    /**
     * List recent audit-trail entries for the current tenant.
     */
    public function index(Request $request): Response
    {
        $logs = AuditLog::query()
            ->with('user:id,name')
            ->latest()
            ->limit(200)
            ->get()
            ->map(fn (AuditLog $log): array => [
                'id' => $log->id,
                'event' => $log->event,
                'model' => class_basename($log->auditable_type),
                'record_id' => $log->auditable_id,
                'user' => $log->user_id === null ? 'Sistem' : $log->user->name,
                'ip_address' => $log->ip_address,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at->toIso8601String(),
            ]);

        return Inertia::render('audit/index', [
            'logs' => $logs,
        ]);
    }
}
