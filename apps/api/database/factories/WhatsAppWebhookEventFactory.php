<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppWebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppWebhookEvent>
 */
class WhatsAppWebhookEventFactory extends Factory
{
    protected $model = WhatsAppWebhookEvent::class;

    public function definition(): array
    {
        $account = WhatsAppAccount::factory()->create();

        return [
            'organization_id' => $account->organization_id,
            'whatsapp_account_id' => $account->getKey(),
            'event_type' => 'messages',
            'payload' => ['object' => 'whatsapp_business_account'],
            'headers' => ['content-type' => ['application/json']],
            'processing_status' => 'pending',
            'processed_at' => null,
            'error_message' => null,
        ];
    }
}
