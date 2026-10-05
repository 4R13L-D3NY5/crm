<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

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
            Log::warning("Baileys webhook received for unknown account: {$accountId}");
            return response()->json(['error' => 'Account not found'], 404);
        }

        match ($event) {
            'qr' => $this->handleQr($account, $request),
            'connected' => $this->handleConnected($account, $request),
            'disconnected' => $this->handleDisconnected($account, $request),
            'message' => $this->handleMessage($account, $request),
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

        if (blank($body) || blank($fromPhone)) {
            return;
        }

        $cleanPhone = preg_replace('/[^0-9+]/', '', $fromPhone);

        // 1. Contacto
        $contact = Contact::firstOrCreate(
            [
                'organization_id' => $account->organization_id,
                'phone' => filled($cleanPhone) ? $cleanPhone : null,
            ],
            [
                'first_name' => filled($fromName) ? $fromName : $cleanPhone,
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
                'status' => 'pending',
                'unread_count' => 0,
                'last_message_at' => Carbon::now(),
            ]);
        }

        $conversation->increment('unread_count');
        $conversation->update(['last_message_at' => Carbon::now()]);

        // 3. Crear mensaje entrante
        $msg = Message::create([
            'organization_id' => $account->organization_id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'message_status' => 'delivered',
            'body' => $body,
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
                    'direction' => 'inbound',
                    'status' => 'delivered',
                    'from_phone' => $cleanPhone,
                    'payload' => $request->all(),
                ]
            );
        }

        // 5. Emitir evento de Socket en tiempo real
        event(new TicketMessageCreatedEvent($msg));
    }
}
