<?php

namespace App\Modules\Conversations\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ConversationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'channel' => $this->channel,
            'whatsapp_account_id' => $this->whatsapp_account_id,
            'channel_account' => $this->whatsappAccount ? [
                'id' => $this->whatsappAccount->id,
                'name' => $this->whatsappAccount->name,
                'display_phone_number' => $this->whatsappAccount->display_phone_number,
                'session_type' => $this->whatsappAccount->session_type,
            ] : null,
            'custom_status_id' => $this->custom_status_id,
            'custom_status' => $this->customStatus ? [
                'id' => $this->customStatus->id,
                'name' => $this->customStatus->name,
                'color' => $this->customStatus->color,
                'icon' => $this->customStatus->icon,
                'stage_type' => $this->customStatus->stage_type,
            ] : null,
            'categories' => $this->relationLoaded('categories') ? $this->categories->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
                'color' => $c->color,
                'icon' => $c->icon,
            ])->values() : [],
            'status' => $this->status,
            'subject' => $this->subject,
            'unread_count' => $this->unread_count ?? 0,
            'is_group' => (bool) $this->is_group,
            'last_message_at' => $this->last_message_at?->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'rating' => $this->rating,
            'feedback' => $this->feedback,
            'queue' => $this->queue ? [
                'id' => $this->queue->id,
                'name' => $this->queue->name,
                'color' => $this->queue->color,
            ] : null,
            'contact' => $this->contact ? [
                'id' => $this->contact->id,
                'name' => $this->contact->name,
                'phone' => $this->contact->phone,
                'email' => $this->contact->email,
                'tags' => $this->contact->relationLoaded('tags') ? $this->contact->tags->map(fn ($t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'color' => $t->color ?? null,
                ])->values() : [],
            ] : null,
            'company' => $this->company ? [
                'id' => $this->company->id,
                'name' => $this->company->name,
            ] : null,
            'assigned_to_user_id' => $this->assigned_to_user_id ?? $this->assignment?->assigned_to_user_id,
            'assignment' => $this->assignment ? [
                'id' => $this->assignment->assigned_to_user_id,
                'assigned_to_user_id' => $this->assignment->assigned_to_user_id,
                'assigned_by_user_id' => $this->assignment->assigned_by_user_id,
                'assignee' => $this->assignment->assignee ? [
                    'id' => $this->assignment->assignee->id,
                    'name' => $this->assignment->assignee->name,
                    'email' => $this->assignment->assignee->email,
                ] : null,
            ] : null,
            'assignee' => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'email' => $this->assignee->email,
            ] : ($this->assignment?->assignee ? [
                'id' => $this->assignment->assignee->id,
                'name' => $this->assignment->assignee->name,
                'email' => $this->assignment->assignee->email,
            ] : null),
            'latest_message' => $this->whenLoaded(
                'latestMessage',
                fn () => $this->latestMessage ? (new MessageResource($this->latestMessage))->resolve() : null,
            ),
            'messages' => MessageResource::collection($this->whenLoaded('messages')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
