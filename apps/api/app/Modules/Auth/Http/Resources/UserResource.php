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
            'phone' => $this->phone,
            'avatar_url' => $this->avatar_url,
            'presence_status' => $this->presence_status ?? 'online',
            'last_seen_at' => $this->last_seen_at?->toIso8601String(),
            'preferences' => $this->preferences_with_defaults,
            'two_factor_enabled' => $this->hasTwoFactorEnabled(),
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
