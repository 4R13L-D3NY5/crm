<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Http\Resources\WhatsAppWebhookEventResource;
use App\Modules\WhatsApp\Models\WhatsAppWebhookEvent;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WhatsAppWebhookEventController
{
    public function index(): AnonymousResourceCollection
    {
        $events = WhatsAppWebhookEvent::query()
            ->where('organization_id', request()->user()->current_organization_id)
            ->latest()
            ->paginate((int) request()->integer('per_page', 20))
            ->withQueryString();

        return WhatsAppWebhookEventResource::collection($events);
    }
}
