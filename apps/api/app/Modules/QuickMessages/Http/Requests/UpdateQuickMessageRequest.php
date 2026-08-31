<?php

namespace App\Modules\QuickMessages\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateQuickMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'shortcut' => ['sometimes', 'required', 'string', 'max:50'],
            'message' => ['sometimes', 'required', 'string'],
            'media_url' => ['nullable', 'string'],
            'media_type' => ['nullable', 'string', 'max:30'],
            'is_general' => ['nullable', 'boolean'],
        ];
    }
}
