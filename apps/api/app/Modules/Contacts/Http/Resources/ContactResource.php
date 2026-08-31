<?php

namespace App\Modules\Contacts\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'notes' => $this->notes,
            'tags' => $this->tags->map(fn ($tag) => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])->values(),
            'companies' => $this->whenLoaded('companies', fn () => $this->companies->map(fn ($company) => [
                'id' => $company->id,
                'name' => $company->name,
                'industry' => $company->industry,
                'status' => $company->status,
            ])->values()),
            'deals' => $this->whenLoaded('deals', fn () => $this->deals->map(fn ($deal) => [
                'id' => $deal->id,
                'name' => $deal->name,
                'status' => $deal->status,
                'amount' => (float) $deal->amount,
                'expected_close_date' => $deal->expected_close_date?->toDateString(),
                'company' => $deal->company ? [
                    'id' => $deal->company->id,
                    'name' => $deal->company->name,
                ] : null,
            ])->values()),
            'conversations' => $this->whenLoaded('conversations', fn () => $this->conversations->map(fn ($conversation) => [
                'id' => $conversation->id,
                'channel' => $conversation->channel,
                'status' => $conversation->status,
                'subject' => $conversation->subject,
                'last_message_at' => $conversation->last_message_at?->toIso8601String(),
                'assignee' => $conversation->assignment?->assignee ? [
                    'id' => $conversation->assignment->assignee->id,
                    'name' => $conversation->assignment->assignee->name,
                ] : null,
            ])->values()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
