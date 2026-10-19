<?php

namespace App\Modules\Conversations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListConversationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:open,pending,resolved,closed'],
            'tab' => ['nullable', 'string', 'in:attending,pending,closed'],
            'whatsapp_account_id' => ['nullable', 'string'],
            'queue_id' => ['nullable', 'string'],
            'assigned_to' => ['nullable', 'in:me,unassigned'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
