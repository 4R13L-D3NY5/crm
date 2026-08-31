<?php

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\Company;
use Illuminate\Support\Arr;

class UpdateCompanyAction
{
    public function execute(Company $company, array $payload): Company
    {
        $company->update([
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
