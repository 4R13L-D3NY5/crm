<?php

namespace App\Modules\Companies\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'name' => $this->name,
            'industry' => $this->industry,
            'website' => $this->website,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'notes' => $this->notes,
            'contacts' => $this->contacts->map(fn ($contact) => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
                'status' => $contact->status,
            ])->values(),
            'deals' => $this->whenLoaded('deals', fn () => $this->deals->map(fn ($deal) => [
                'id' => $deal->id,
                'name' => $deal->name,
                'status' => $deal->status,
                'amount' => (float) $deal->amount,
                'expected_close_date' => $deal->expected_close_date?->toDateString(),
                'contact' => $deal->contact ? [
                    'id' => $deal->contact->id,
                    'name' => $deal->contact->name,
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
