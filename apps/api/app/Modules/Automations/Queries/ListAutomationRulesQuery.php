<?php

namespace App\Modules\Automations\Queries;

use App\Models\User;
use App\Modules\Automations\Models\AutomationRule;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListAutomationRulesQuery
{
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return AutomationRule::query()
            ->where('organization_id', $user->current_organization_id)
            ->when($filters['trigger_type'] ?? null, fn ($query, $triggerType) => $query->where('trigger_type', $triggerType))
            ->when(
                array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '',
                fn ($query) => $query->where('is_active', (bool) $filters['is_active']),
            )
            ->latest()
            ->paginate($perPage)
            ->withQueryString();
    }
}
