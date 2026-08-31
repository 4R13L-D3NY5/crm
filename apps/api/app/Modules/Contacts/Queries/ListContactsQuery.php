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
            ->with('tags')
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
            ->when($filters['tag'] ?? null, function (Builder $query, string $tag) {
                $query->whereHas('tags', function (Builder $tagQuery) use ($tag) {
                    $tagQuery->where('slug', $tag)->orWhere('name', 'like', "%{$tag}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
