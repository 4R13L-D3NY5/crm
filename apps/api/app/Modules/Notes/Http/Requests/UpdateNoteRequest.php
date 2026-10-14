<?php

namespace App\Modules\Notes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'color' => ['nullable', 'string', 'max:30'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_archived' => ['nullable', 'boolean'],
            'shortcut' => ['nullable', 'string', 'max:50'],
            'visibility' => ['nullable', 'string', 'in:all,categories,private'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'custom_status_ids' => ['nullable', 'array'],
            'custom_status_ids.*' => ['exists:custom_statuses,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
