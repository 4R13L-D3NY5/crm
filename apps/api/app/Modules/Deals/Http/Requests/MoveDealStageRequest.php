<?php

namespace App\Modules\Deals\Http\Requests;

use App\Modules\Deals\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;

class MoveDealStageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Deal $deal */
        $deal = $this->route('deal');

        return $this->user()?->can('update', $deal) ?? false;
    }

    public function rules(): array
    {
        return [
            'pipeline_stage_id' => ['required', 'string', 'exists:pipeline_stages,id'],
        ];
    }
}
