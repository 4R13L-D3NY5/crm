<?php

namespace App\Modules\Notes\Http\Requests;

use App\Modules\Notes\Models\Note;
use Illuminate\Foundation\Http\FormRequest;

class UpdateNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        $noteId = $this->route('id');
        $note = Note::where('organization_id', $user->current_organization_id)->find($noteId);

        return $note && $note->user_id === $user->id;
    }

    public function rules(): array
    {
        return [
            'title' => ['nullable', 'string', 'max:255'],
            'content' => ['sometimes', 'required', 'string'],
            'color' => ['nullable', 'string', 'max:30'],
            'is_pinned' => ['nullable', 'boolean'],
            'is_archived' => ['nullable', 'boolean'],
            'shortcut' => ['nullable', 'string', 'max:50'],
            'visibility' => ['nullable', 'string', 'in:all,categories,private'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'custom_status_ids' => ['nullable', 'array'],
            'custom_status_ids.*' => ['exists:custom_statuses,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['exists:tags,id'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
