<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Reports\Http\Resources\DashboardReportResource;
use App\Modules\Reports\Queries\GetDashboardReportQuery;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardReportController
{
    public function __invoke(
        Request $request,
        GetDashboardReportQuery $getDashboardReportQuery,
    ): JsonResponse {
        $organization = $request->attributes->get('current_organization');

        abort_unless($organization instanceof Organization, 404, 'No hay organizacion activa.');
        abort_unless($request->user()?->can('view', $organization), 403);

        $report = $getDashboardReportQuery->execute($organization);

        return response()->json([
            'data' => (new DashboardReportResource($report))->resolve(),
        ]);
    }
}
