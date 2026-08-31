<?php

namespace App\Modules\Audit\Http\Controllers;

use App\Modules\Audit\Http\Requests\ListAuditLogsRequest;
use App\Modules\Audit\Http\Resources\AuditLogResource;
use App\Modules\Audit\Policies\AuditLogPolicy;
use App\Modules\Audit\Queries\ListAuditLogsQuery;
use App\Shared\Models\AuditLog;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AuditLogController
{
    public function index(
        ListAuditLogsRequest $request,
        ListAuditLogsQuery $listAuditLogsQuery,
    ): AnonymousResourceCollection {
        abort_unless($request->user()?->can('viewAny', AuditLog::class), 403);

        $logs = $listAuditLogsQuery->execute($request->user(), $request->validated());

        return AuditLogResource::collection($logs);
    }
}
