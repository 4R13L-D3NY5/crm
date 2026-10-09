<?php

namespace App\Modules\Contacts\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:active,lead,inactive'],
            'channel' => ['nullable', 'string', 'max:50'],
            'custom_status_id' => ['nullable'],
            'custom_status_ids' => ['nullable'],
            'category_id' => ['nullable'],
            'category_ids' => ['nullable'],
            'tag' => ['nullable'],
            'tags' => ['nullable'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
