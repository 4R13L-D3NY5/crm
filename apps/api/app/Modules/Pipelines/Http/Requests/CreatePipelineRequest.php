<?php

namespace App\Modules\Pipelines\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'is_default' => ['nullable', 'boolean'],
        ];
    }
}
