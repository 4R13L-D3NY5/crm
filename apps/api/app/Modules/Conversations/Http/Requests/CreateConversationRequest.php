<?php

namespace App\Modules\Conversations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:160'],
            'status' => ['required', 'in:open,pending,resolved'],
            'channel' => ['nullable', 'in:manual,whatsapp,email'],
            'contact_id' => ['nullable', 'string', 'exists:contacts,id'],
            'company_id' => ['nullable', 'string', 'exists:companies,id'],
            'message' => ['required', 'string', 'max:5000'],
            'assigned_to_user_id' => ['nullable', 'string', 'exists:users,id'],
        ];
    }
}
