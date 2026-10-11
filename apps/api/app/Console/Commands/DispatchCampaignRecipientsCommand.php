<?php

namespace App\Console\Commands;

use App\Modules\Automations\Models\Campaign;
use App\Modules\Automations\Models\CampaignRecipient;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchCampaignRecipientsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:dispatch-recipients {--limit=10 : Maximum recipients to process per campaign in this run}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch pending campaign recipients for active processing campaigns';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $activeCampaigns = Campaign::where('status', 'processing')->get();

        if ($activeCampaigns->isEmpty()) {
            return 0;
        }

        $limit = (int) $this->option('limit');

        foreach ($activeCampaigns as $campaign) {
            $this->info("Processing campaign: {$campaign->name} (ID: {$campaign->id})");

            $pendingRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->take($limit)
                ->get();

            if ($pendingRecipients->isEmpty()) {
                // Si ya no quedan destinatarios pendientes, la campaña se marca completada
                $remainingCount = CampaignRecipient::where('campaign_id', $campaign->id)
                    ->where('status', 'pending')
                    ->count();

                if ($remainingCount === 0) {
                    $campaign->update([
                        'status' => 'completed',
                        'completed_at' => Carbon::now(),
                    ]);
                    $this->info("✓ Campaign '{$campaign->name}' marked as completed.");
                }
                continue;
            }

            foreach ($pendingRecipients as $recipient) {
                try {
                    // Contacto
                    $contact = $recipient->contact;
                    if (!$contact) {
                        $contact = Contact::firstOrCreate(
                            [
                                'organization_id' => $campaign->organization_id,
                                'phone' => $recipient->phone,
                            ],
                            [
                                'first_name' => $recipient->name ?: 'Contacto',
                                'status' => 'active',
                            ]
                        );
                    }

                    // Reemplazo dinámico de variables
                    $body = $campaign->message_template;
                    $recipientName = $recipient->name ?: ($contact->first_name ?: 'Estimado/a');
                    $body = str_replace(
                        ['{{name}}', '{{nombre}}', '{name}', '{nombre}', '{{phone}}', '{{telefono}}'],
                        [$recipientName, $recipientName, $recipientName, $recipientName, $recipient->phone, $recipient->phone],
                        $body
                    );

                    // Conversación
                    $conversation = Conversation::firstOrCreate(
                        [
                            'organization_id' => $campaign->organization_id,
                            'contact_id' => $contact->id,
                            'channel' => 'whatsapp',
                        ],
                        [
                            'status' => 'open',
                            'last_message_at' => now(),
                        ]
                    );

                    // Mensaje
                    $message = Message::create([
                        'organization_id' => $campaign->organization_id,
                        'conversation_id' => $conversation->id,
                        'user_id' => null,
                        'direction' => 'outbound',
                        'message_type' => 'text',
                        'message_status' => 'pending',
                        'body' => $body,
                        'sent_at' => null,
                    ]);

                    if ($campaign->whatsapp_account_id) {
                        WhatsAppMessageMapping::create([
                            'organization_id' => $campaign->organization_id,
                            'conversation_id' => $conversation->id,
                            'whatsapp_account_id' => $campaign->whatsapp_account_id,
                            'message_id' => $message->id,
                            'direction' => 'outbound',
                            'status' => 'pending',
                        ]);
                    }

                    SendWhatsAppMessageJob::dispatch($message->id);

                    $recipient->update([
                        'status' => 'sent',
                        'sent_at' => now(),
                    ]);

                    $campaign->increment('sent_count');
                    $this->line("  ✓ Sent to {$recipient->phone} ({$recipientName})");
                } catch (Throwable $e) {
                    Log::error("Failed campaign recipient {$recipient->id}: {$e->getMessage()}");
                    $recipient->update([
                        'status' => 'failed',
                        'error_message' => $e->getMessage(),
                    ]);
                    $campaign->increment('failed_count');
                    $this->error("  ✗ Failed for {$recipient->phone}: {$e->getMessage()}");
                }
            }

            // Verificar si tras este lote ya no quedan destinatarios pendientes
            $remaining = CampaignRecipient::where('campaign_id', $campaign->id)
                ->where('status', 'pending')
                ->count();

            if ($remaining === 0) {
                $campaign->update([
                    'status' => 'completed',
                    'completed_at' => Carbon::now(),
                ]);
                $this->info("✓ Campaign '{$campaign->name}' marked as completed.");
            }
        }

        return 0;
    }
}
