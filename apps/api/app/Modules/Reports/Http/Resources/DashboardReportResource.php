<?php

namespace App\Modules\Reports\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'summary' => $this['summary'],
            'conversation_channels' => $this['conversation_channels'],
            'conversation_statuses' => $this['conversation_statuses'],
            'deal_statuses' => $this['deal_statuses'],
            'recent_activity' => $this['recent_activity'],
        ];
    }
}
