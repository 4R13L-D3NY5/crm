<?php

namespace App\Modules\Contacts\Queries;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListContactsQuery
{
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return Contact::query()
            ->with(['tags', 'customStatus', 'categories', 'conversations.categories', 'conversations.whatsappAccount'])
            ->where('organization_id', $user->current_organization_id)
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['custom_status_id'] ?? $filters['custom_status_ids'] ?? null, function (Builder $query, $statusIds) {
                $ids = is_array($statusIds) ? $statusIds : explode(',', (string) $statusIds);
                $ids = array_filter(array_map('trim', $ids));
                if (!empty($ids)) {
                    $query->whereIn('custom_status_id', $ids);
                }
            })
            ->when($filters['category_id'] ?? $filters['category_ids'] ?? null, function (Builder $query, $catIds) {
                $ids = is_array($catIds) ? $catIds : explode(',', (string) $catIds);
                $ids = array_filter(array_map('trim', $ids));
                if (!empty($ids)) {
                    $query->where(function (Builder $sub) use ($ids) {
                        $sub->whereHas('categories', fn ($catQ) => $catQ->whereIn('categories.id', $ids))
                            ->orWhereHas('conversations.categories', fn ($catQ) => $catQ->whereIn('categories.id', $ids));
                    });
                }
            })
            ->when($filters['tag'] ?? $filters['tags'] ?? null, function (Builder $query, $tags) {
                $tagList = is_array($tags) ? $tags : explode(',', (string) $tags);
                $tagList = array_filter(array_map('trim', $tagList));
                if (!empty($tagList)) {
                    $query->whereHas('tags', function (Builder $tagQuery) use ($tagList) {
                        $tagQuery->whereIn('slug', $tagList)->orWhereIn('name', $tagList);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
