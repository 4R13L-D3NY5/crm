<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $conversation = Conversation::factory()->create();

        return [
            'organization_id' => $conversation->organization_id,
            'conversation_id' => $conversation->getKey(),
            'user_id' => User::factory(),
            'direction' => fake()->randomElement(['internal', 'inbound', 'outbound']),
            'message_type' => 'text',
            'body' => fake()->paragraph(),
            'sent_at' => now(),
        ];
    }
}
