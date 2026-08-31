<?php

namespace App\Modules\WhatsApp\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertWhatsAppAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'phone_number_id' => ['required', 'string', 'max:80'],
            'display_phone_number' => ['nullable', 'string', 'max:40'],
            'business_account_id' => ['nullable', 'string', 'max:80'],
            'verify_token' => ['required', 'string', 'max:120'],
            'access_token' => ['nullable', 'string', 'max:2048'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
