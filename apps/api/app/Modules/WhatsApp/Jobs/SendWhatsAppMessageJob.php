<?php

namespace App\Modules\WhatsApp\Jobs;

use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use App\Modules\WhatsApp\Services\WhatsAppClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendWhatsAppMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function __construct(
        public string $messageId,
    ) {
        $this->onQueue((string) config('crm.queues.whatsapp', 'whatsapp'));
    }

    public function handle(WhatsAppClient $client): void
    {
        $message = Message::query()->with(['conversation.contact'])->find($this->messageId);

        if (! $message) {
            return;
        }

        $accountId = WhatsAppMessageMapping::query()
            ->where('conversation_id', $message->conversation_id)
            ->whereNotNull('whatsapp_account_id')
            ->latest()
            ->value('whatsapp_account_id');

        $account = null;
        if ($accountId) {
            $account = WhatsAppAccount::query()
                ->where('organization_id', $message->organization_id)
                ->where('id', $accountId)
                ->first();
        }

        if (! $account) {
            $account = WhatsAppAccount::query()
                ->where('organization_id', $message->organization_id)
                ->where('is_active', true)
                ->where('status', 'CONNECTED')
                ->latest('last_connected_at')
                ->first();
        }

        if (! $account) {
            $account = WhatsAppAccount::query()
                ->where('organization_id', $message->organization_id)
                ->where('is_active', true)
                ->first();
        }

        if (! $account) {
            $message->forceFill([
                'message_status' => 'failed',
                'error_message' => 'No existe una cuenta activa de WhatsApp.',
            ])->save();

            return;
        }

        $to = $message->conversation?->contact?->phone;

        if (! $to) {
            $message->forceFill([
                'message_status' => 'failed',
                'error_message' => 'La conversacion no tiene un telefono destino.',
            ])->save();

            return;
        }

        try {
            $isMetaCloud = $account->session_type === 'meta_cloud' || (filled($account->access_token) && filled($account->phone_number_id));

            if ($isMetaCloud) {
                $response = $client->sendTextMessage($account, $to, $message->body);
            } else {
                $baileysClient = app(\App\Modules\WhatsApp\Services\BaileysWhatsAppClient::class);
                $response = $baileysClient->sendTextMessage($account, $to, $message->body);
            }

            $providerMessageId = data_get($response, 'messages.0.id');

            $message->forceFill([
                'message_status' => 'sent',
                'error_message' => null,
                'sent_at' => now(),
            ])->save();

            if ($providerMessageId) {
                WhatsAppMessageMapping::query()->updateOrCreate(
                    [
                        'provider_message_id' => $providerMessageId,
                    ],
                    [
                        'organization_id' => $message->organization_id,
                        'whatsapp_account_id' => $account->getKey(),
                        'contact_id' => $message->conversation?->contact_id,
                        'conversation_id' => $message->conversation_id,
                        'message_id' => $message->getKey(),
                        'direction' => 'outbound',
                        'status' => 'sent',
                        'from_phone' => $to,
                        'to_phone_number_id' => $account->phone_number_id,
                        'payload' => $response,
                    ],
                );
            }
        } catch (Throwable $exception) {
            $message->forceFill([
                'message_status' => 'failed',
                'error_message' => $exception->getMessage(),
            ])->save();
        }
    }
}
