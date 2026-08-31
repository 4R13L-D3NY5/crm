<?php

namespace App\Modules\Conversations\Http\Requests;

use App\Modules\Conversations\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class AssignConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Conversation $conversation */
        $conversation = $this->route('conversation');

        return $this->user()?->can('update', $conversation) ?? false;
    }

    public function rules(): array
    {
        return [
            'assigned_to_user_id' => ['nullable', 'string', 'exists:users,id'],
        ];
    }
}
