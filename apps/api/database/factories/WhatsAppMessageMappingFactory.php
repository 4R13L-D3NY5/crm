<?php

namespace Database\Factories;

use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppMessageMapping>
 */
class WhatsAppMessageMappingFactory extends Factory
{
    protected $model = WhatsAppMessageMapping::class;

    public function definition(): array
    {
        $account = WhatsAppAccount::factory()->create();

        return [
            'organization_id' => $account->organization_id,
            'whatsapp_account_id' => $account->getKey(),
            'provider_message_id' => 'wamid.'.fake()->unique()->lexify('????????????'),
            'direction' => 'inbound',
            'status' => 'received',
            'from_phone' => '+59170000001',
            'to_phone_number_id' => $account->phone_number_id,
            'payload' => ['type' => 'text'],
        ];
    }
}
