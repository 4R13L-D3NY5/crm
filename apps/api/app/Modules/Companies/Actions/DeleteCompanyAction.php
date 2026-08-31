<?php

namespace App\Modules\Companies\Actions;

use App\Modules\Companies\Models\Company;

class DeleteCompanyAction
{
    public function execute(Company $company): void
    {
        $company->delete();
    }
}
