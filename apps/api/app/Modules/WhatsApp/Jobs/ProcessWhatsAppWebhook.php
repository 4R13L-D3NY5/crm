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
        $sentAt = isset($messagePayload['timestamp']) ? Carbon::createFromTimestamp((int) $messagePayload['timestamp']) : now();

        $rawType = $messagePayload['type'] ?? 'text';
        $mediaType = null;
        $mediaUrl = null;
        $mediaId = null;
        $mimeType = null;
        $caption = null;

        switch ($rawType) {
            case 'text':
                $body = $messagePayload['text']['body'] ?? '';
                break;
            case 'image':
                $mediaType = 'image';
                $caption = $messagePayload['image']['caption'] ?? null;
                $mediaId = $messagePayload['image']['id'] ?? null;
                $mimeType = $messagePayload['image']['mime_type'] ?? 'image/jpeg';
                $body = $caption ? "📷 [Imagen]: {$caption}" : '📷 [Imagen]';
                break;
            case 'audio':
            case 'voice':
                $mediaType = 'audio';
                $mediaId = $messagePayload['audio']['id'] ?? $messagePayload['voice']['id'] ?? null;
                $mimeType = $messagePayload['audio']['mime_type'] ?? $messagePayload['voice']['mime_type'] ?? 'audio/ogg';
                $isVoice = ! empty($messagePayload['audio']['voice']) || $rawType === 'voice';
                $body = $isVoice ? '🎤 [Nota de voz]' : '🎵 [Audio]';
                break;
            case 'video':
                $mediaType = 'video';
                $caption = $messagePayload['video']['caption'] ?? null;
                $mediaId = $messagePayload['video']['id'] ?? null;
                $mimeType = $messagePayload['video']['mime_type'] ?? 'video/mp4';
                $body = $caption ? "🎥 [Video]: {$caption}" : '🎥 [Video]';
                break;
            case 'document':
                $mediaType = 'document';
                $filename = $messagePayload['document']['filename'] ?? 'archivo';
                $caption = $messagePayload['document']['caption'] ?? null;
                $mediaId = $messagePayload['document']['id'] ?? null;
                $mimeType = $messagePayload['document']['mime_type'] ?? 'application/pdf';
                $body = $caption ? "📄 [Documento] {$filename}: {$caption}" : "📄 [Documento]: {$filename}";
                break;
            case 'sticker':
                $mediaType = 'sticker';
                $mediaId = $messagePayload['sticker']['id'] ?? null;
                $mimeType = $messagePayload['sticker']['mime_type'] ?? 'image/webp';
                $body = '🏷️ [Sticker]';
                break;
            case 'location':
                $loc = $messagePayload['location'] ?? [];
                $name = $loc['name'] ?? $loc['address'] ?? '';
                $body = "📍 [Ubicación]: {$loc['latitude']}, {$loc['longitude']}".($name ? " ({$name})" : '');
                break;
            case 'contacts':
                $contactName = data_get($messagePayload, 'contacts.0.name.formatted_name', 'Contacto');
                $body = "👤 [Contacto compartido]: {$contactName}";
                break;
            case 'button':
            case 'interactive':
                $interactiveText = data_get($messagePayload, 'interactive.button_reply.title')
                    ?? data_get($messagePayload, 'interactive.list_reply.title')
                    ?? data_get($messagePayload, 'button.text');
                $body = $interactiveText ? "🔘 {$interactiveText}" : '[Respuesta interactiva]';
                break;
            default:
                $body = $messagePayload['text']['body'] ?? "[Mensaje ({$rawType})]";
                break;
        }

        // Si viene multimedia en Cloud API, intentamos descargarla a storage público
        if ($mediaId && filled($account->access_token)) {
            try {
                /** @var \App\Modules\WhatsApp\Services\MetaWhatsAppClient $metaClient */
                $metaClient = app(\App\Modules\WhatsApp\Services\MetaWhatsAppClient::class);
                $mediaUrl = $metaClient->downloadMedia($account, $mediaId, $mimeType);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error intentando descargar media Meta {$mediaId}: {$e->getMessage()}");
            }
        }

        $rawReferral = $messagePayload['referral'] ?? null;
        $referral = null;
        if (is_array($rawReferral)) {
            $referral = [
                'source_type' => strtolower($rawReferral['source_type'] ?? 'ad'),
                'source_id' => $rawReferral['source_id'] ?? null,
                'source_url' => $rawReferral['source_url'] ?? null,
                'headline' => $rawReferral['headline'] ?? null,
                'body' => $rawReferral['body'] ?? null,
                'media_type' => $rawReferral['media_type'] ?? 'image',
                'image_url' => $rawReferral['image_url'] ?? null,
                'video_url' => $rawReferral['video_url'] ?? null,
                'thumbnail_url' => $rawReferral['thumbnail_url'] ?? null,
                'ctwa_clid' => $rawReferral['ctwa_clid'] ?? null,
            ];
        }

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
            $lastClosed = Conversation::query()
                ->where('organization_id', $account->organization_id)
                ->where('contact_id', $contact->getKey())
                ->where('channel', 'whatsapp')
                ->latest('last_message_at')
                ->first();

            if ($lastClosed) {
                // Si el cliente respondió a la encuesta de satisfacción CSAT del ticket cerrado
                $csatService = app(\App\Modules\Conversations\Services\CsatSurveyService::class);
                if ($csatService->isEligibleForSurveyReply($lastClosed, $body)) {
                    $csatService->recordSurveyReply($lastClosed, $body, $account, $providerMessageId, $rawPayload);
                    return;
                }

                $conversation = $lastClosed;
                $convUpdates = [
                    'status' => 'pending',
                    'closed_at' => null,
                    'whatsapp_account_id' => $account->getKey(),
                ];
                if ($referral && empty($conversation->origin_referral)) {
                    $convUpdates['origin_referral'] = $referral;
                    $convUpdates['metadata'] = array_merge((array) ($conversation->metadata ?? []), ['origin_referral' => $referral]);
                }
                $conversation->forceFill($convUpdates)->save();
            } else {
                $conversation = Conversation::query()->create([
                    'organization_id' => $account->organization_id,
                    'contact_id' => $contact->getKey(),
                    'company_id' => null,
                    'created_by_user_id' => null,
                    'channel' => 'whatsapp',
                    'whatsapp_account_id' => $account->getKey(),
                    'status' => 'pending',
                    'subject' => 'WhatsApp: '.$profileName,
                    'last_message_at' => $sentAt,
                    'origin_referral' => $referral,
                    'metadata' => $referral ? ['origin_referral' => $referral] : null,
                ]);
            }
        } else {
            $convUpdates = [];
            if (! $conversation->whatsapp_account_id) {
                $convUpdates['whatsapp_account_id'] = $account->getKey();
            }
            if ($referral && empty($conversation->origin_referral)) {
                $convUpdates['origin_referral'] = $referral;
                $convUpdates['metadata'] = array_merge((array) ($conversation->metadata ?? []), ['origin_referral' => $referral]);
            }
            if (! empty($convUpdates)) {
                $conversation->forceFill($convUpdates)->save();
            }
        }

        $messageMetadata = [];
        if ($referral) {
            $messageMetadata['referral'] = $referral;
        }
        if ($mediaId) {
            $messageMetadata['media_id'] = $mediaId;
        }
        if ($caption) {
            $messageMetadata['caption'] = $caption;
        }
        if ($mimeType) {
            $messageMetadata['mime_type'] = $mimeType;
        }

        $message = Message::query()->create([
            'organization_id' => $account->organization_id,
            'conversation_id' => $conversation->getKey(),
            'user_id' => null,
            'direction' => 'inbound',
            'message_type' => $mediaType ?? ($messagePayload['type'] ?? 'text'),
            'media_type' => $mediaType,
            'media_url' => $mediaUrl,
            'message_status' => 'received',
            'body' => $body,
            'metadata' => ! empty($messageMetadata) ? $messageMetadata : null,
            'sent_at' => $sentAt,
        ]);

        if ($mediaType === 'audio' && $mediaUrl) {
            try {
                app(\App\Modules\Conversations\Services\VoiceTranscriptionService::class)->transcribe($message);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("Error en transcripción de audio para mensaje {$message->id}: {$e->getMessage()}");
            }
        }

        if ($referral) {
            $cf = (array) ($contact->custom_fields ?? []);
            $cf['last_ad_referral'] = $referral;
            $contact->update(['custom_fields' => $cf]);
        }

        $conversation->increment('unread_count');
        $conversation->forceFill([
            'last_message_at' => $sentAt,
            'status' => $conversation->assigned_to_user_id ? 'open' : 'pending',
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

        // Evaluar radar de sentimiento y alerta de frustración en tiempo real
        try {
            $analyzer = app(\App\Modules\HentleAi\Services\AiConversationAnalyzerService::class);
            $analysis = $analyzer->analyze($conversation);
            $analyzer->checkAndAlertFrustration($conversation, $analysis);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Sentiment radar evaluation failed in Cloud API: ' . $e->getMessage());
        }

        // Auto-respuesta si entra fuera de horario laboral
        $outOfHoursMsg = app(\App\Modules\Parameters\Actions\DispatchOutOfHoursAutoReplyAction::class)->execute($conversation);

        // Flujo de Bot de Bienvenida / Enrutamiento de Filas si no fue fuera de horario
        if (! $outOfHoursMsg) {
            $textBody = $messagePayload['text']['body'] ?? null;
            $botHandled = app(\App\Modules\Queues\Actions\DispatchBotFlowAction::class)->execute($conversation, $textBody);

            // Si no fue atendido por BotFlow y la conversación sigue sin asignar, evaluar Smart AI Semi-Immediate Reply
            if (! $botHandled) {
                $setting = \App\Modules\Settings\Models\WorkspaceSetting::where('organization_id', $account->organization_id)->first();
                $aiConfig = $setting?->custom_options['ai_config'] ?? [];
                $smartReplyEnabled = (bool) ($aiConfig['smart_reply_enabled'] ?? false);
                if ($smartReplyEnabled) {
                    $delaySec = max(5, (int) ($aiConfig['smart_reply_delay_seconds'] ?? 15));
                    \App\Modules\HentleAi\Jobs\ProcessSmartAiReplyJob::dispatch($conversation->getKey(), $message->getKey())
                        ->delay(now()->addSeconds($delaySec));
                }
            }
        }
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
