<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class WhatsAppQrSessionController extends Controller
{
    /**
     * Genera o retorna el código QR dinámico de emparejamiento
     */
    public function getQr(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $account = WhatsAppAccount::where('organization_id', $organization->id)->findOrFail($id);

        $sessionId = Str::uuid()->toString();
        $timestamp = Carbon::now()->timestamp;
        // String de emparejamiento estándar compatible con WhatsApp Web / Baileys
        $pairingCode = "2@{$sessionId},{$timestamp},XPERTI-FLOW-CRM,{$account->id}";

        $account->update([
            'qrcode_raw' => $pairingCode,
            'status' => 'CONNECTING',
        ]);

        return response()->json([
            'data' => [
                'account_id' => $account->id,
                'status' => 'CONNECTING',
                'qrcode_raw' => $pairingCode,
                'expires_in_seconds' => 60,
                'generated_at' => Carbon::now()->toIso8601String(),
            ],
            'message' => 'Código QR de sincronización generado exitosamente.',
        ]);
    }

    /**
     * Simula el escaneo con el celular y cambia el estado a CONNECTED
     */
    public function simulateScan(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $account = WhatsAppAccount::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        $account->update([
            'status' => 'CONNECTED',
            'phone_number' => $validated['phone_number'] ?? ($account->phone_number ?: '+59144252525'),
            'qrcode_raw' => null,
            'last_connected_at' => Carbon::now(),
            'is_active' => true,
        ]);

        return response()->json([
            'data' => [
                'account_id' => $account->id,
                'status' => 'CONNECTED',
                'phone_number' => $account->phone_number,
                'connected_at' => $account->last_connected_at->toIso8601String(),
            ],
            'message' => 'Sesión de WhatsApp vinculada y conectada exitosamente.',
        ]);
    }

    /**
     * Desconecta la sesión de WhatsApp
     */
    public function disconnect(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $account = WhatsAppAccount::where('organization_id', $organization->id)->findOrFail($id);

        $account->update([
            'status' => 'DISCONNECTED',
            'qrcode_raw' => null,
        ]);

        return response()->json([
            'data' => [
                'account_id' => $account->id,
                'status' => 'DISCONNECTED',
            ],
            'message' => 'Sesión de WhatsApp desconectada.',
        ]);
    }

    /**
     * Inyecta un mensaje entrante simulado para verificar flujo en vivo
     */
    public function simulateIncoming(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $account = WhatsAppAccount::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'from_phone' => ['required', 'string'],
            'from_name' => ['nullable', 'string'],
            'message' => ['required', 'string'],
        ]);

        $cleanPhone = preg_replace('/[^0-9+]/', '', $validated['from_phone']);
        $channel = match ($account->session_type ?? 'baileys_qr') {
            'facebook' => 'facebook',
            'instagram' => 'instagram',
            'tiktok' => 'tiktok',
            default => 'whatsapp',
        };

        // 1. Buscar o crear contacto
        if ($channel === 'instagram') {
            $username = ltrim($validated['from_phone'], '@');
            $contact = Contact::firstOrCreate(
                [
                    'organization_id' => $organization->id,
                    'first_name' => $validated['from_name'] ?: "@{$username}",
                ],
                [
                    'phone' => filled($cleanPhone) ? $cleanPhone : null,
                    'status' => 'active',
                    'notes' => "Lead simulado de Instagram Direct @{$username}",
                    'custom_fields' => [
                        'instagram_id' => 'sim_' . time(),
                        'instagram_username' => $username,
                    ],
                ]
            );
        } else {
            $contact = Contact::firstOrCreate(
                ['organization_id' => $organization->id, 'phone' => filled($cleanPhone) ? $cleanPhone : null],
                ['first_name' => $validated['from_name'] ?? 'Usuario ' . ucfirst($channel), 'status' => 'active']
            );
        }

        // 2. Buscar conversación abierta o crear nueva
        $conversation = Conversation::firstOrCreate(
            [
                'organization_id' => $organization->id,
                'contact_id' => $contact->id,
                'status' => 'pending',
            ],
            [
                'channel' => $channel,
                'whatsapp_account_id' => $account->id,
                'unread_count' => 0,
                'last_message_at' => Carbon::now(),
            ]
        );

        $conversation->increment('unread_count');
        $conversation->update(['last_message_at' => Carbon::now()]);

        // 3. Crear mensaje entrante
        $msg = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'body' => $validated['message'],
            'sent_at' => Carbon::now(),
        ]);

        // 4. Emitir evento de Socket en tiempo real
        event(new TicketMessageCreatedEvent($msg));

        return response()->json([
            'data' => [
                'conversation_id' => $conversation->id,
                'contact' => $contact,
                'message' => $msg,
            ],
            'message' => 'Mensaje entrante procesado y emitido por WebSockets en vivo.',
        ], 201);
    }
}
