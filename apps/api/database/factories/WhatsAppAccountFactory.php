<?php

namespace Database\Factories;

use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppAccount>
 */
class WhatsAppAccountFactory extends Factory
{
    protected $model = WhatsAppAccount::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'WhatsApp Demo',
            'phone_number_id' => (string) fake()->unique()->numberBetween(1000000000, 9999999999),
            'display_phone_number' => '+59170000001',
            'business_account_id' => (string) fake()->numberBetween(1000000000, 9999999999),
            'verify_token' => 'verify-token-demo',
            'access_token' => 'meta-access-token-demo',
            'is_active' => true,
        ];
    }
}
