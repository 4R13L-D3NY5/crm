<?php

namespace App\Modules\Pipelines\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreatePipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'position' => ['required', 'integer', 'min:1'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
