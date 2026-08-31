<?php

namespace Database\Factories;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversationAssignment>
 */
class ConversationAssignmentFactory extends Factory
{
    protected $model = ConversationAssignment::class;

    public function definition(): array
    {
        return [
            'conversation_id' => Conversation::factory(),
            'assigned_to_user_id' => User::factory(),
            'assigned_by_user_id' => User::factory(),
        ];
    }
}
