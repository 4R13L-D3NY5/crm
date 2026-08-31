<?php

namespace App\Shared\Actions;

use App\Models\User;
use App\Shared\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class WriteAuditLogAction
{
    public function execute(
        string $event,
        ?User $user = null,
        ?Request $request = null,
        ?Model $auditable = null,
        array $metadata = [],
        ?string $organizationId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'organization_id' => $organizationId
                ?? $user?->current_organization_id
                ?? data_get($auditable, 'organization_id'),
            'user_id' => $user?->getKey(),
            'event' => $event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
