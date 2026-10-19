<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Events\TicketMessageUpdatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BaileysWebhookController extends Controller
{
    public function handleWebhook(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $accountId = $request->input('account_id');

        if (! $accountId) {
            return response()->json(['error' => 'Missing account_id'], 400);
        }

        $account = WhatsAppAccount::find($accountId);

        if (! $account) {
            Log::info("Baileys webhook received for unknown/deleted account: {$accountId}");
            return response()->json(['status' => 'ignored', 'message' => 'Account not found'], 200);
        }

        match ($event) {
            'qr' => $this->handleQr($account, $request),
            'connected' => $this->handleConnected($account, $request),
            'disconnected' => $this->handleDisconnected($account, $request),
            'message' => $this->handleMessage($account, $request),
            'message_edited' => $this->handleMessageEdited($account, $request),
            default => Log::info("Baileys unhandled event: {$event}"),
        };

        return response()->json(['status' => 'ok']);
    }

    protected function handleQr(WhatsAppAccount $account, Request $request): void
    {
        $qrRaw = $request->input('qr_image') ?: $request->input('qr_raw');

        $account->update([
            'qrcode_raw' => $qrRaw,
            'status' => 'CONNECTING',
        ]);
    }

    protected function handleConnected(WhatsAppAccount $account, Request $request): void
    {
        $phone = $request->input('phone');

        $account->update([
            'status' => 'CONNECTED',
            'display_phone_number' => filled($phone) ? $phone : $account->display_phone_number,
            'qrcode_raw' => null,
            'last_connected_at' => Carbon::now(),
            'is_active' => true,
        ]);

        Log::info("Baileys session connected successfully for account {$account->id} ({$phone})");
    }

    protected function handleDisconnected(WhatsAppAccount $account, Request $request): void
    {
        $account->update([
            'status' => 'DISCONNECTED',
            'qrcode_raw' => null,
        ]);

        Log::info("Baileys session disconnected for account {$account->id}");
    }

    protected function handleMessage(WhatsAppAccount $account, Request $request): void
    {
        $fromPhone = $request->input('from_phone');
        $fromName = $request->input('from_name');
        $body = $request->input('body');
        $providerMessageId = $request->input('provider_message_id');
        $isFromMe = $request->boolean('from_me');
        $mediaBase64 = $request->input('media_base64');
        $mediaType = $request->input('media_type');
        $mimeType = $request->input('mime_type');
        $durationSeconds = $request->input('media_duration_seconds');

        // Deduplicación preventiva: si el mensaje ya fue registrado previamente en el CRM
        // (por ejemplo, enviado desde la interfaz web vía CRM o procesado previamente), no duplicar.
        if ($providerMessageId && WhatsAppMessageMapping::where('provider_message_id', $providerMessageId)->exists()) {
            Log::info("Baileys message {$providerMessageId} already mapped (deduplicated). Skipping.");
            return;
        }

        if (blank($body) && filled($mediaType)) {
            $body = match ($mediaType) {
                'image' => '[Imagen]',
                'audio' => '[Nota de voz / Audio]',
                'video' => '[Video]',
                'document' => '[Documento]',
                'sticker' => '[Sticker]',
                default => '[Multimedia]',
            };
        }

        if (blank($body) || blank($fromPhone)) {
            return;
        }

        $cleanPhone = preg_replace('/[^0-9+]/', '', $fromPhone);

        // Almacenar multimedia si viene adjunto
        $mediaUrl = null;
        if (filled($mediaBase64)) {
            try {
                $binary = base64_decode((string) $mediaBase64);
                if ($binary !== false && strlen($binary) > 0) {
                    $extension = match ($mediaType) {
                        'image' => match ($mimeType) {
                            'image/png' => 'png',
                            'image/webp' => 'webp',
                            'image/gif' => 'gif',
                            default => 'jpg',
                        },
                        'sticker' => 'webp',
                        'audio' => match (true) {
                            str_contains((string) $mimeType, 'mp4') || str_contains((string) $mimeType, 'm4a') => 'm4a',
                            str_contains((string) $mimeType, 'mp3') => 'mp3',
                            default => 'ogg',
                        },
                        'video' => 'mp4',
                        'document' => match ($mimeType) {
                            'application/pdf' => 'pdf',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
                            'application/msword' => 'doc',
                            default => 'bin',
                        },
                        default => 'bin',
                    };

                    $filename = (string) Str::ulid() . '.' . $extension;
                    Storage::disk('public')->put("whatsapp_media/{$filename}", $binary);
                    $mediaUrl = "/storage/whatsapp_media/{$filename}";
                }
            } catch (\Throwable $e) {
                Log::error("Failed to store Baileys media: " . $e->getMessage());
            }
        }

        // 1. Contacto
        $defaultName = $isFromMe ? $cleanPhone : (filled($fromName) ? $fromName : $cleanPhone);
        $contact = Contact::firstOrCreate(
            [
                'organization_id' => $account->organization_id,
                'phone' => filled($cleanPhone) ? $cleanPhone : null,
            ],
            [
                'first_name' => $defaultName,
                'status' => 'active',
            ]
        );

        // 2. Conversación activa
        $conversation = Conversation::where('organization_id', $account->organization_id)
            ->where('contact_id', $contact->id)
            ->where('channel', 'whatsapp')
            ->whereIn('status', ['pending', 'open'])
            ->latest('last_message_at')
            ->first();

        if (! $conversation) {
            $conversation = Conversation::create([
                'organization_id' => $account->organization_id,
                'contact_id' => $contact->id,
                'channel' => 'whatsapp',
                'whatsapp_account_id' => $account->id,
                'status' => $isFromMe ? 'open' : 'pending',
                'unread_count' => 0,
                'last_message_at' => Carbon::now(),
            ]);
        } elseif (! $conversation->whatsapp_account_id) {
            $conversation->update(['whatsapp_account_id' => $account->id]);
        }

        if ($isFromMe) {
            // Sincronización espejo de mensaje enviado fuera del CRM (WhatsApp Web o Móvil Android/iOS)
            $conversation->update([
                'status' => 'open',
                'last_message_at' => Carbon::now(),
            ]);
        } else {
            $conversation->increment('unread_count');
            $conversation->update(['last_message_at' => Carbon::now()]);
        }

        // 3. Crear mensaje (inbound u outbound espejo)
        $direction = $isFromMe ? 'outbound' : 'inbound';
        $msg = Message::create([
            'organization_id' => $account->organization_id,
            'conversation_id' => $conversation->id,
            'user_id' => $isFromMe ? $conversation->assigned_user_id : null,
            'direction' => $direction,
            'message_type' => $mediaType ?: 'text',
            'message_status' => 'delivered',
            'delivery_status' => 'delivered',
            'body' => $body,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'media_duration_seconds' => filled($durationSeconds) ? (int) $durationSeconds : null,
            'sent_at' => Carbon::now(),
        ]);

        // 4. Mapeo de proveedor si existe
        if ($providerMessageId) {
            WhatsAppMessageMapping::query()->updateOrCreate(
                [
                    'provider_message_id' => $providerMessageId,
                ],
                [
                    'organization_id' => $account->organization_id,
                    'whatsapp_account_id' => $account->id,
                    'contact_id' => $contact->id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $msg->id,
                    'direction' => $direction,
                    'status' => 'delivered',
                    'from_phone' => $cleanPhone,
                    'payload' => $request->all(),
                ]
            );
        }

        // 5. Emitir evento de Socket en tiempo real
        event(new TicketMessageCreatedEvent($msg));

        // 6. Verificar y enviar auto-respuesta si el mensaje ingresa fuera de horario laboral
        if (! $isFromMe) {
            $outOfHoursMsg = app(\App\Modules\Parameters\Actions\DispatchOutOfHoursAutoReplyAction::class)->execute($conversation);

            // 7. Si no se activó mensaje de fuera de horario, evaluar flujo de Bot de Bienvenida / Enrutamiento de Filas
            if (! $outOfHoursMsg) {
                app(\App\Modules\Queues\Actions\DispatchBotFlowAction::class)->execute($conversation, $body);
            }
        }
    }

    protected function handleMessageEdited(WhatsAppAccount $account, Request $request): void
    {
        $targetMessageId = $request->input('target_message_id');
        $newBody = $request->input('body');

        if (blank($targetMessageId) || blank($newBody)) {
            return;
        }

        $mapping = WhatsAppMessageMapping::where('provider_message_id', $targetMessageId)
            ->where('whatsapp_account_id', $account->id)
            ->first();

        if (! $mapping || ! $mapping->message_id) {
            Log::info("Baileys message_edited: target provider message {$targetMessageId} not mapped.");
            return;
        }

        $message = Message::find($mapping->message_id);
        if (! $message) {
            return;
        }

        $message->update([
            'original_body' => $message->original_body ?: $message->body,
            'body' => $newBody,
            'is_edited' => true,
            'edited_at' => now(),
        ]);

        event(new TicketMessageUpdatedEvent($message));

        Log::info("Baileys message edited for account {$account->id}, message {$message->id}");
    }
}
