<?php

namespace App\Modules\Companies\Actions;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use Illuminate\Support\Arr;

class CreateCompanyAction
{
    public function execute(User $user, array $payload): Company
    {
        $company = Company::query()->create([
            'organization_id' => $user->current_organization_id,
            'name' => $payload['name'],
            'industry' => $payload['industry'] ?? null,
            'website' => $payload['website'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'status' => $payload['status'],
            'notes' => $payload['notes'] ?? null,
        ]);

        $company->contacts()->sync(Arr::wrap($payload['contact_ids'] ?? []));

        return $company->load('contacts');
    }
}
