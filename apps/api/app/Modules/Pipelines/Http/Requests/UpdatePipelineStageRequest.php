<?php

namespace App\Modules\Pipelines\Http\Requests;

use App\Modules\Pipelines\Models\PipelineStage;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePipelineStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var PipelineStage $stage */
        $stage = $this->route('stage');

        return $this->user()?->can('update', $stage) ?? false;
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
