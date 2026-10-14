<?php

namespace App\Modules\Notes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RefineNoteAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'min:3'],
            'action' => ['required', 'string', 'in:spellcheck,summarize,tone_formal,tone_persuasive,custom'],
            'instruction' => ['nullable', 'string', 'max:255'],
        ];
    }
}
