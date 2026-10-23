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
            // Scope por canales asignados (los operadores no-admin solo ven sus canales autorizados o chats asignados)
            ->when(!$user->isAdmin(), function (Builder $query) use ($user) {
                $query->where(function (Builder $q) use ($user) {
                    $q->where('assigned_to_user_id', $user->id)
                      ->orWhereHas('assignment', fn ($as) => $as->where('assigned_to_user_id', $user->id))
                      ->orWhereHas('whatsappAccount.authorizedUsers', function (Builder $userQuery) use ($user) {
                          $userQuery->where('users.id', $user->id)
                                    ->where('whatsapp_account_users.can_view', true);
                      });
                });
            })
            ->when($filters['whatsapp_account_id'] ?? null, fn (Builder $query, string $accountId) => $query->where('whatsapp_account_id', $accountId))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $search = trim($search);
                $cleanPhone = preg_replace('/\D+/', '', $search);

                $query->where(function (Builder $nested) use ($search, $cleanPhone) {
                    $nested
                        ->where('subject', 'ilike', "%{$search}%")
                        ->orWhereHas('contact', function (Builder $contactQuery) use ($search, $cleanPhone) {
                            $contactQuery->where(function (Builder $cq) use ($search, $cleanPhone) {
                                $cq->where('first_name', 'ilike', "%{$search}%")
                                   ->orWhere('last_name', 'ilike', "%{$search}%")
                                   ->orWhereRaw("CONCAT(COALESCE(first_name, ''), ' ', COALESCE(last_name, '')) ILIKE ?", ["%{$search}%"])
                                   ->orWhere('email', 'ilike', "%{$search}%")
                                   ->orWhere('phone', 'ilike', "%{$search}%");

                                if (!empty($cleanPhone) && strlen($cleanPhone) >= 4) {
                                    $cq->orWhereRaw("REGEXP_REPLACE(COALESCE(phone, ''), '\D', '', 'g') ILIKE ?", ["%{$cleanPhone}%"]);
                                }
                            });
                        })
                        ->orWhereHas('company', fn (Builder $companyQuery) => $companyQuery->where('name', 'ilike', "%{$search}%"))
                        ->orWhereHas('latestMessage', fn (Builder $messageQuery) => $messageQuery->where('body', 'ilike', "%{$search}%"));
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
