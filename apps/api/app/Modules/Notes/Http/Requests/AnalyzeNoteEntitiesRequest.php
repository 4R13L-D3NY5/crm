<?php

namespace App\Modules\Notes\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeNoteEntitiesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['required', 'string'],
        ];
    }
}
