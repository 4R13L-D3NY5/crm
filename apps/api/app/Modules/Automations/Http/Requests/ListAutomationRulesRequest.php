<?php

namespace App\Modules\Automations\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListAutomationRulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'trigger_type' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
