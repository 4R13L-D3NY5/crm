<?php

namespace App\Shared\Support;

use Illuminate\Support\Facades\Bus;

class DispatchDomainJob
{
    public static function dispatch(object $job, ?string $mode = null): void
    {
        $dispatchMode = $mode ?? config('crm.dispatch_mode', 'sync');

        if ($dispatchMode === 'async') {
            Bus::dispatch($job);

            return;
        }

        Bus::dispatchSync($job);
    }
}
