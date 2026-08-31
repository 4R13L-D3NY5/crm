<?php

namespace App\Modules\Deals\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateDealRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
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
