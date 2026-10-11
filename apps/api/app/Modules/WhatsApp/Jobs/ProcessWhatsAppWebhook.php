<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\Automations\Jobs\RunAutomationRulesJob;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use App\Modules\WhatsApp\Models\WhatsAppWebhookEvent;
use Illuminate\Support\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessWhatsAppWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 3;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public string $webhookEventId,
    ) {
        $this->onQueue((string) config('crm.queues.whatsapp', 'whatsapp'));
    }

    public function handle(): void
    {
        $event = WhatsAppWebhookEvent::query()->find($this->webhookEventId);

        if (! $event) {
            return;
        }

        try {
            foreach ($event->payload['entry'] ?? [] as $entry) {
                foreach ($entry['changes'] ?? [] as $change) {
                    $value = $change['value'] ?? [];
                    $phoneNumberId = $value['metadata']['phone_number_id'] ?? null;
                    $account = $event->account ?? WhatsAppAccount::query()
                        ->where('phone_number_id', $phoneNumberId)
                        ->first();

                    if (! $account) {
                        continue;
                    }

                    $event->forceFill([
                        'organization_id' => $account->organization_id,
                        'whatsapp_account_id' => $account->getKey(),
                        'event_type' => $change['field'] ?? 'messages',
                    ])->save();

                    $contacts = collect($value['contacts'] ?? [])->keyBy('wa_id');

                    foreach ($value['messages'] ?? [] as $messagePayload) {
                        $this->processInboundMessage($account, $messagePayload, $contacts->get($messagePayload['from']), $messagePayload);
                    }

                    foreach ($value['statuses'] ?? [] as $statusPayload) {
                        $this->processStatusUpdate($statusPayload);
                    }
                }
            }

            $event->forceFill([
                'processing_status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ])->save();
        } catch (Throwable $exception) {
            $event->forceFill([
                'processing_status' => 'failed',
                'processed_at' => now(),
                'error_message' => $exception->getMessage(),
            ])->save();

            throw $exception;
        }
    }

    private function processInboundMessage(
        WhatsAppAccount $account,
        array $messagePayload,
        ?array $contactPayload,
        array $rawPayload,
    ): void {
        $providerMessageId = $messagePayload['id'] ?? null;

        if (! $providerMessageId) {
            return;
        }

        $existingMapping = WhatsAppMessageMapping::query()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if ($existingMapping) {
            return;
        }

        $phone = $messagePayload['from'] ?? null;
        $profileName = $contactPayload['profile']['name'] ?? $phone ?? 'Contacto WhatsApp';
        $body = $messagePayload['text']['body'] ?? '[mensaje sin soporte en MVP]';
        $sentAt = isset($messagePayload['timestamp']) ? Carbon::createFromTimestamp((int) $messagePayload['timestamp']) : now();

        $contact = Contact::query()
            ->where('organization_id', $account->organization_id)
            ->where('phone', $phone)
            ->first();

        if (! $contact) {
            $contact = Contact::query()->create([
                'organization_id' => $account->organization_id,
                'first_name' => $profileName,
                'last_name' => null,
                'email' => null,
                'phone' => $phone,
                'status' => 'lead',
                'notes' => 'Creado automaticamente desde webhook de WhatsApp.',
            ]);
        }

        $conversation = Conversation::query()
            ->where('organization_id', $account->organization_id)
            ->where('contact_id', $contact->getKey())
            ->where('channel', 'whatsapp')
            ->whereIn('status', ['open', 'pending'])
            ->latest('last_message_at')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::query()->create([
                'organization_id' => $account->organization_id,
                'contact_id' => $contact->getKey(),
                'company_id' => null,
                'created_by_user_id' => null,
                'channel' => 'whatsapp',
                'whatsapp_account_id' => $account->getKey(),
                'status' => 'open',
                'subject' => 'WhatsApp: '.$profileName,
                'last_message_at' => $sentAt,
            ]);
        } elseif (! $conversation->whatsapp_account_id) {
            $conversation->forceFill(['whatsapp_account_id' => $account->getKey()])->save();
        }

        $message = Message::query()->create([
            'organization_id' => $account->organization_id,
            'conversation_id' => $conversation->getKey(),
            'user_id' => null,
            'direction' => 'inbound',
            'message_type' => $messagePayload['type'] ?? 'text',
            'message_status' => 'received',
            'body' => $body,
            'sent_at' => $sentAt,
        ]);

        $conversation->forceFill([
            'last_message_at' => $sentAt,
            'status' => 'open',
        ])->save();

        WhatsAppMessageMapping::query()->create([
            'organization_id' => $account->organization_id,
            'whatsapp_account_id' => $account->getKey(),
            'contact_id' => $contact->getKey(),
            'conversation_id' => $conversation->getKey(),
            'message_id' => $message->getKey(),
            'provider_message_id' => $providerMessageId,
            'direction' => 'inbound',
            'status' => 'received',
            'from_phone' => $phone,
            'to_phone_number_id' => $account->phone_number_id,
            'payload' => $rawPayload,
        ]);

        \App\Shared\Support\DispatchDomainJob::dispatch(new RunAutomationRulesJob(
            $account->organization_id,
            'message.inbound.received',
            $conversation->getKey(),
            $message->getKey(),
        ));
    }

    private function processStatusUpdate(array $statusPayload): void
    {
        $providerMessageId = $statusPayload['id'] ?? null;

        if (! $providerMessageId) {
            return;
        }

        $mapping = WhatsAppMessageMapping::query()
            ->where('provider_message_id', $providerMessageId)
            ->first();

        if (! $mapping) {
            return;
        }

        $status = $statusPayload['status'] ?? 'sent';
        $errorMessage = collect($statusPayload['errors'] ?? [])->pluck('title')->filter()->implode(', ');

        $mapping->forceFill([
            'status' => $status,
            'payload' => $statusPayload,
        ])->save();

        if ($mapping->message) {
            $mapping->message->forceFill([
                'message_status' => $status === 'failed' ? 'failed' : $status,
                'error_message' => $errorMessage ?: null,
                'sent_at' => $mapping->message->sent_at ?? now(),
            ])->save();
        }
    }
}
