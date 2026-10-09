<?php

namespace App\Modules\Conversations\Queries;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListConversationsQuery
{
    public function execute(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 20);

        return Conversation::query()
            ->with(['contact.tags', 'company', 'queue', 'assignee', 'assignment.assignee', 'latestMessage.user', 'customStatus', 'categories', 'whatsappAccount'])
            ->where('organization_id', $user->current_organization_id)
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $nested) use ($search) {
                    $nested
                        ->where('subject', 'like', "%{$search}%")
                        ->orWhereHas('contact', function (Builder $contactQuery) use ($search) {
                            $contactQuery
                                ->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('phone', 'like', "%{$search}%");
                        })
                        ->orWhereHas('company', fn (Builder $companyQuery) => $companyQuery->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('latestMessage', fn (Builder $messageQuery) => $messageQuery->where('body', 'like', "%{$search}%"));
                });
            })
            // Filtro por pestañas Whaticket
            ->when($filters['tab'] ?? null, function (Builder $query, string $tab) use ($user) {
                if ($tab === 'attending') {
                    $query->where('status', 'open')
                        ->where(function ($sub) use ($user) {
                            $sub->where('assigned_to_user_id', $user->id)
                                ->orWhereHas('assignment', fn ($as) => $as->where('assigned_to_user_id', $user->id));
                        });
                } elseif ($tab === 'pending') {
                    $query->where(function ($sub) {
                        $sub->where('status', 'pending')
                            ->orWhere(function ($s2) {
                                $s2->where('status', 'open')
                                    ->whereNull('assigned_to_user_id')
                                    ->whereDoesntHave('assignment');
                            });
                    });
                } elseif ($tab === 'closed') {
                    $query->where('status', 'closed');
                }
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['queue_id'] ?? null, fn (Builder $query, string $queueId) => $query->where('queue_id', $queueId))
            ->when($filters['assigned_to'] ?? null, function (Builder $query, string $assignedTo) use ($user) {
                if ($assignedTo === 'me') {
                    $query->where('assigned_to_user_id', $user->id);
                } elseif ($assignedTo === 'unassigned') {
                    $query->whereNull('assigned_to_user_id');
                }
            })
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}
