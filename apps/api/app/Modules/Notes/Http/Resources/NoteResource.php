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
        $mediaUrl = $this->image_url ?? data_get($this->metadata, 'image_url');
        $isSubVideo = false;
        if ($mediaUrl) {
            $cleanPath = parse_url($mediaUrl, PHP_URL_PATH) ?? $mediaUrl;
            $ext = strtolower(pathinfo($cleanPath, PATHINFO_EXTENSION));
            $isSubVideo = in_array($ext, ['mp4', 'webm', 'mov', 'ogg', 'm4v', 'quicktime']);
        }

        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'user_id' => $this->user_id,
            'user' => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ] : null,
            'author_name' => $this->user?->name ?? 'Usuario',
            'title' => $this->title,
            'content' => $this->content,
            'image_url' => $mediaUrl,
            'media_url' => $mediaUrl,
            'media_type' => $isSubVideo ? 'video' : ($mediaUrl ? 'image' : null),
            'color' => $this->color ?? '#1e293b',
            'is_pinned' => (bool) $this->is_pinned,
            'is_archived' => (bool) $this->is_archived,
            'shortcut' => $this->shortcut,
            'visibility' => $this->visibility ?? 'all',
            'metadata' => $this->metadata,
            'categories' => $this->whenLoaded('categories', fn () => $this->categories->map(fn ($cat) => [
                'id' => $cat->id,
                'parent_id' => $cat->parent_id,
                'name' => $cat->name,
                'code' => $cat->code,
                'color' => $cat->color,
                'icon' => $cat->icon,
                'slug' => $cat->slug,
                'display_path' => $cat->display_path,
                'full_path' => $cat->full_path,
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
