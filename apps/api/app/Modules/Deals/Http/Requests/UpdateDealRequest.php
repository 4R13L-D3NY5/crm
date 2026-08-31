<?php

namespace App\Modules\Deals\Http\Requests;

use App\Modules\Deals\Models\Deal;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDealRequest extends FormRequest
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
            'pipeline_id' => ['required', 'string', 'exists:pipelines,id'],
            'pipeline_stage_id' => ['required', 'string', 'exists:pipeline_stages,id'],
            'name' => ['required', 'string', 'max:150'],
            'status' => ['required', 'in:open,won,lost'],
            'amount' => ['required', 'numeric', 'min:0'],
            'probability' => ['required', 'integer', 'min:0', 'max:100'],
            'expected_close_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'contact_id' => ['nullable', 'string', 'exists:contacts,id'],
            'company_id' => ['nullable', 'string', 'exists:companies,id'],
        ];
    }
}
