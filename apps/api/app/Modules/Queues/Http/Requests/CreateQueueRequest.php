<?php

namespace App\Modules\Queues\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateQueueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'string', 'max:20'],
            'greeting_message' => ['nullable', 'string', 'max:1000'],
            'out_of_hours_message' => ['nullable', 'string', 'max:1000'],
            'order_index' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'chatbot_options' => ['nullable', 'array'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['string', 'exists:users,id'],
        ];
    }
}
