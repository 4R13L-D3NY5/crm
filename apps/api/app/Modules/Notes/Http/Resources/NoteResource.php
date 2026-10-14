<?php

namespace App\Modules\Notes\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NoteResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'title' => $this->title,
            'content' => $this->content,
            'color' => $this->color ?? '#1e293b',
            'is_pinned' => (bool) $this->is_pinned,
            'is_archived' => (bool) $this->is_archived,
            'shortcut' => $this->shortcut,
            'visibility' => $this->visibility ?? 'all',
            'metadata' => $this->metadata,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($cat) => [
                'id' => $cat->id,
                'name' => $cat->name,
                'color' => $cat->color,
                'icon' => $cat->icon,
                'slug' => $cat->slug,
            ])),
            'custom_statuses' => $this->whenLoaded('customStatuses', fn () => $this->customStatuses->map(fn ($st) => [
                'id' => $st->id,
                'name' => $st->name,
                'color' => $st->color,
                'icon' => $st->icon,
                'slug' => $st->slug,
            ])),
            'tags' => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'color_hex' => $tag->color_hex,
                'slug' => $tag->slug,
            ])),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
