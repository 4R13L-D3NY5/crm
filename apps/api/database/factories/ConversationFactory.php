<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'created_by_user_id' => User::factory(),
            'channel' => 'manual',
            'status' => fake()->randomElement(['open', 'pending', 'resolved']),
            'subject' => fake()->sentence(4),
            'last_message_at' => now(),
        ];
    }
}
