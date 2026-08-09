<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditLogger
{
    public function record(
        Request $request,
        string $action,
        ?Model $subject = null,
        array $old = [],
        array $new = [],
        array $metadata = [],
        ?int $actorId = null,
    ): AuditLog {
        if (app()->bound(ImpersonationManager::class)) {
            $metadata = array_merge(app(ImpersonationManager::class)->auditContext($request), $metadata);
        }

        return AuditLog::create([
            'actor_id' => $actorId ?? $request->user()?->id,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'metadata' => $metadata ?: null,
            'ip_address' => $request->ip(),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
