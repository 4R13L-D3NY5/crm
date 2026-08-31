<?php

namespace App\Modules\QuickMessages\Actions;

use App\Modules\QuickMessages\Models\QuickMessage;

class DeleteQuickMessageAction
{
    public function execute(QuickMessage $quickMessage): void
    {
        $quickMessage->delete();
    }
}
