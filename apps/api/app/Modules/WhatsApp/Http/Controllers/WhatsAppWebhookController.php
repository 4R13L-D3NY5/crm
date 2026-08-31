<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Jobs\ProcessWhatsAppWebhook;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppWebhookEvent;
use App\Shared\Actions\WriteAuditLogAction;
use App\Shared\Support\DispatchDomainJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsAppWebhookController
{
    public function verify(Request $request): JsonResponse|\Symfony\Component\HttpFoundation\Response
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $account = WhatsAppAccount::query()
            ->where('verify_token', $token)
            ->where('is_active', true)
            ->first();

        if ($mode === 'subscribe' && $account && $challenge !== null) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json([
            'message' => 'Webhook verification failed.',
        ], 403);
    }

    public function receive(
        Request $request,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse
    {
        $payload = $request->all();
        $phoneNumberId = data_get($payload, 'entry.0.changes.0.value.metadata.phone_number_id');
        $account = $phoneNumberId
            ? WhatsAppAccount::query()->where('phone_number_id', $phoneNumberId)->first()
            : null;

        $event = WhatsAppWebhookEvent::query()->create([
            'organization_id' => $account?->organization_id,
            'whatsapp_account_id' => $account?->getKey(),
            'event_type' => data_get($payload, 'entry.0.changes.0.field'),
            'payload' => $payload,
            'headers' => $request->headers->all(),
            'processing_status' => 'pending',
        ]);
        $writeAuditLogAction->execute(
            event: 'whatsapp.webhook_received',
            request: $request,
            auditable: $event,
            metadata: [
                'event_type' => $event->event_type,
                'phone_number_id' => $phoneNumberId,
            ],
            organizationId: $account?->organization_id,
        );

        DispatchDomainJob::dispatch(new ProcessWhatsAppWebhook($event->getKey()));

        return response()->json([
            'received' => true,
        ], 202);
    }
}
