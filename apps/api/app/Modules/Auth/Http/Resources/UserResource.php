<?php

namespace App\Modules\Auth\Http\Resources;

use App\Modules\Tenancy\Http\Resources\OrganizationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'current_role' => $this->currentOrganizationRole(),
            'permissions' => $this->permissions(),
            'current_organization' => $this->whenLoaded(
                'currentOrganization',
                fn () => $this->currentOrganization ? new OrganizationResource($this->currentOrganization) : null,
            ),
            'organizations' => OrganizationResource::collection(
                $this->whenLoaded('organizations'),
            ),
        ];
    }
}
