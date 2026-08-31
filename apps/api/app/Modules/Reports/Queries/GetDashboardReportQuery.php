<?php

namespace App\Modules\Reports\Queries;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Deals\Models\Deal;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Collection;

class GetDashboardReportQuery
{
    public function execute(Organization $organization): array
    {
        $organizationId = $organization->getKey();

        $openConversations = Conversation::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'open')
            ->count();

        $pendingConversations = Conversation::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'pending')
            ->count();

        $resolvedConversations = Conversation::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'resolved')
            ->count();

        $activeDeals = Deal::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'open')
            ->count();

        $wonDeals = Deal::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'won')
            ->count();

        $estimatedRevenue = (float) Deal::query()
            ->where('organization_id', $organizationId)
            ->where('status', 'open')
            ->sum('amount');

        $contactsTotal = Contact::query()
            ->where('organization_id', $organizationId)
            ->count();

        $inboundMessagesToday = Message::query()
            ->where('organization_id', $organizationId)
            ->where('direction', 'inbound')
            ->whereDate('created_at', now()->toDateString())
            ->count();

        return [
            'summary' => [
                'open_conversations' => $openConversations,
                'pending_conversations' => $pendingConversations,
                'resolved_conversations' => $resolvedConversations,
                'active_deals' => $activeDeals,
                'won_deals' => $wonDeals,
                'estimated_revenue' => round($estimatedRevenue, 2),
                'contacts_total' => $contactsTotal,
                'inbound_messages_today' => $inboundMessagesToday,
            ],
            'conversation_channels' => Conversation::query()
                ->selectRaw('channel, count(*) as total')
                ->where('organization_id', $organizationId)
                ->groupBy('channel')
                ->orderByDesc('total')
                ->get()
                ->map(static fn (Conversation $conversation): array => [
                    'channel' => $conversation->channel,
                    'total' => (int) $conversation->total,
                ])
                ->values()
                ->all(),
            'conversation_statuses' => Conversation::query()
                ->selectRaw('status, count(*) as total')
                ->where('organization_id', $organizationId)
                ->groupBy('status')
                ->orderByDesc('total')
                ->get()
                ->map(static fn (Conversation $conversation): array => [
                    'status' => $conversation->status,
                    'total' => (int) $conversation->total,
                ])
                ->values()
                ->all(),
            'deal_statuses' => Deal::query()
                ->selectRaw('status, count(*) as total, coalesce(sum(amount), 0) as total_amount')
                ->where('organization_id', $organizationId)
                ->groupBy('status')
                ->orderByDesc('total')
                ->get()
                ->map(static fn (Deal $deal): array => [
                    'status' => $deal->status,
                    'total' => (int) $deal->total,
                    'total_amount' => round((float) $deal->total_amount, 2),
                ])
                ->values()
                ->all(),
            'recent_activity' => $this->recentActivity($organization),
        ];
    }

    private function recentActivity(Organization $organization): array
    {
        $organizationId = $organization->getKey();

        $conversationEvents = Conversation::query()
            ->where('organization_id', $organizationId)
            ->with(['contact', 'company'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(static function (Conversation $conversation): array {
                $subject = $conversation->subject ?: 'Conversacion sin asunto';
                $relatedName = $conversation->contact?->name
                    ?: $conversation->company?->name
                    ?: 'Sin contacto asociado';

                return [
                    'type' => 'conversation',
                    'title' => $subject,
                    'description' => sprintf(
                        'Conversacion %s en %s.',
                        $conversation->status,
                        ucfirst($conversation->channel)
                    ),
                    'meta' => $relatedName,
                    'href' => "/app/conversations?conversation={$conversation->getKey()}",
                    'occurred_at' => ($conversation->last_message_at ?? $conversation->updated_at)->toIso8601String(),
                ];
            });

        $dealEvents = Deal::query()
            ->where('organization_id', $organizationId)
            ->with(['stage', 'company', 'contact'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(static function (Deal $deal): array {
                $relatedName = $deal->company?->name
                    ?: $deal->contact?->name
                    ?: 'Sin relacion asociada';

                return [
                    'type' => 'deal',
                    'title' => $deal->name,
                    'description' => sprintf(
                        'Deal %s en etapa %s.',
                        $deal->status,
                        $deal->stage?->name ?? 'sin etapa'
                    ),
                    'meta' => $relatedName,
                    'href' => '/app/deals',
                    'occurred_at' => $deal->updated_at->toIso8601String(),
                ];
            });

        return Collection::make()
            ->merge($conversationEvents)
            ->merge($dealEvents)
            ->sortByDesc('occurred_at')
            ->take(6)
            ->values()
            ->all();
    }
}
