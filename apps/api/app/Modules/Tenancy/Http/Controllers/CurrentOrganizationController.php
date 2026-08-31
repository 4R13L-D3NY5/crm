<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Modules\Tenancy\Actions\SwitchCurrentOrganizationAction;
use App\Modules\Tenancy\Http\Requests\SwitchCurrentOrganizationRequest;
use App\Modules\Tenancy\Http\Resources\OrganizationResource;
use App\Modules\Tenancy\Models\Organization;
use App\Shared\Actions\WriteAuditLogAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CurrentOrganizationController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user()->load('currentOrganization');

        return response()->json([
            'data' => $user->currentOrganization ? new OrganizationResource($user->currentOrganization) : null,
        ]);
    }

    public function update(
        SwitchCurrentOrganizationRequest $request,
        SwitchCurrentOrganizationAction $switchAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse {
        $organization = Organization::query()->findOrFail($request->validated('organization_id'));
        $previousOrganizationId = $request->user()->current_organization_id;

        Gate::authorize('switch', $organization);

        $user = $switchAction->execute($request->user(), $organization);
        $writeAuditLogAction->execute(
            event: 'tenancy.organization_switched',
            user: $user,
            request: $request,
            auditable: $organization,
            metadata: [
                'previous_organization_id' => $previousOrganizationId,
                'current_organization_id' => $organization->getKey(),
            ],
            organizationId: $organization->getKey(),
        );

        return response()->json([
            'data' => new OrganizationResource($user->currentOrganization),
            'message' => 'Organizacion actualizada correctamente.',
        ]);
    }
}
