<?php

namespace App\Modules\Automations\Http\Requests;

use App\Modules\Automations\Models\AutomationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateAutomationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var AutomationRule $rule */
        $rule = $this->route('automationRule');

        return $this->user()?->can('update', $rule) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'trigger_type' => ['required', 'in:conversation.created,message.inbound.received'],
            'conditions' => ['nullable', 'array'],
            'conditions.channel' => ['nullable', 'in:manual,whatsapp,email'],
            'conditions.message_contains' => ['nullable', 'string', 'max:120'],
            'actions' => ['required', 'array', 'min:1'],
            'actions.*.type' => ['required', 'in:assign_user,set_status,send_whatsapp_message'],
            'actions.*.value' => ['required', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
