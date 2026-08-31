<?php

namespace App\Modules\Deals\Actions;

use App\Modules\Deals\Models\Deal;

class DeleteDealAction
{
    public function execute(Deal $deal): void
    {
        $deal->delete();
    }
}
