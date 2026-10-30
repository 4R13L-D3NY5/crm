<?php

namespace App\Modules\Tenancy\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrganizationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $now = Carbon::now();
        $expiresAt = $this->expires_at ? Carbon::parse($this->expires_at) : null;
        $daysRemaining = $expiresAt ? (int) $now->copy()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false) : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'role' => $this->whenPivotLoaded('organization_user', fn () => $this->pivot->role),
            'plan' => $this->plan ?? 'basic',
            'status' => $this->status ?? 'active',
            'billing_cycle' => $this->billing_cycle ?? 'monthly',
            'expires_at' => $expiresAt?->toIso8601String(),
            'days_remaining' => $daysRemaining,
            'is_trial' => $this->status === 'trial',
            'is_expired' => $expiresAt ? $now->isAfter($expiresAt) : false,
        ];
    }
}
