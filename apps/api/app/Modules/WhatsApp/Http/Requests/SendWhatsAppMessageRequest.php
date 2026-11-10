<?php

namespace App\Modules\WhatsApp\Http\Requests;

use App\Modules\Conversations\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

class SendWhatsAppMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Conversation $conversation */
        $conversation = $this->route('conversation');

        return $this->user()?->can('update', $conversation) ?? false;
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:5000', 'required_without_all:file,media_url,template_name,is_template'],
            'file' => ['nullable', 'file', 'max:51200'],
            'media_url' => ['nullable', 'string', 'max:2000'],
            'media_type' => ['nullable', 'string', 'in:image,audio,document,video,sticker'],
            'is_template' => ['nullable', 'boolean'],
            'template_name' => ['nullable', 'string', 'max:255'],
            'template_language' => ['nullable', 'string', 'max:20'],
            'template_params' => ['nullable', 'array'],
        ];
    }
}
