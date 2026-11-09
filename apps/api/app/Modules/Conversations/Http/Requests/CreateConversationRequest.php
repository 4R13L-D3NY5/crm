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
            'status' => ['nullable', 'in:open,pending,resolved,closed'],
            'channel' => ['nullable', 'string', 'max:50'],
            'whatsapp_account_id' => ['nullable', 'string', 'exists:whatsapp_accounts,id'],
            'contact_id' => ['nullable', 'string', 'exists:contacts,id'],
            'phone' => ['nullable', 'string', 'max:50'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'company_id' => ['nullable', 'string', 'exists:companies,id'],
            'message' => ['required', 'string', 'max:5000'],
            'assigned_to_user_id' => ['nullable', 'string', 'exists:users,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $channel = $this->input('channel', 'manual');
            $hasAccountId = filled($this->input('whatsapp_account_id'));
            
            if ($channel === 'whatsapp' || $hasAccountId) {
                if (blank($this->input('contact_id')) && blank($this->input('phone'))) {
                    $validator->errors()->add('phone', 'Debes seleccionar un contacto existente o ingresar un número de teléfono destino.');
                }
            }
        });
    }
}
