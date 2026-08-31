<?php

namespace App\Modules\Automations\Jobs;

use App\Modules\Automations\Models\AutomationRule;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Shared\Support\DispatchDomainJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunAutomationRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function backoff(): array
    {
        return [15, 60];
    }

    public function __construct(
        public string $organizationId,
        public string $triggerType,
        public string $conversationId,
        public ?string $messageId = null,
    ) {
        $this->onQueue((string) config('crm.queues.automations', 'automations'));
    }

    public function handle(): void
    {
        $conversation = Conversation::query()->with('assignment')->find($this->conversationId);
        $message = $this->messageId ? Message::query()->find($this->messageId) : null;

        if (! $conversation) {
            return;
        }

        $rules = AutomationRule::query()
            ->where('organization_id', $this->organizationId)
            ->where('trigger_type', $this->triggerType)
            ->where('is_active', true)
            ->get();

        foreach ($rules as $rule) {
            $run = AutomationRun::query()->create([
                'organization_id' => $this->organizationId,
                'automation_rule_id' => $rule->getKey(),
                'trigger_type' => $this->triggerType,
                'status' => 'completed',
                'context' => [
                    'conversation_id' => $conversation->getKey(),
                    'message_id' => $message?->getKey(),
                    'channel' => $conversation->channel,
                    'body' => $message?->body,
                ],
            ]);

            try {
                if (! $this->matchesConditions($rule, $conversation, $message)) {
                    $run->update([
                        'result' => ['matched' => false],
                    ]);

                    continue;
                }

                $appliedActions = [];

                foreach ($rule->actions ?? [] as $action) {
                    if (($action['type'] ?? null) === 'assign_user') {
                        ConversationAssignment::query()->updateOrCreate(
                            ['conversation_id' => $conversation->getKey()],
                            [
                                'assigned_to_user_id' => $action['value'],
                                'assigned_by_user_id' => null,
                            ],
                        );
                        $appliedActions[] = 'assign_user';
                    }

                    if (($action['type'] ?? null) === 'set_status') {
                        $conversation->forceFill([
                            'status' => $action['value'],
                        ])->save();
                        $appliedActions[] = 'set_status';
                    }

                    if (($action['type'] ?? null) === 'send_whatsapp_message') {
                        if ($conversation->channel !== 'whatsapp' || $this->alreadySentWhatsAppMessage($rule, $conversation)) {
                            continue;
                        }

                        $outboundMessage = Message::query()->create([
                            'organization_id' => $conversation->organization_id,
                            'conversation_id' => $conversation->getKey(),
                            'user_id' => null,
                            'direction' => 'outbound',
                            'message_type' => 'text',
                            'message_status' => 'pending',
                            'body' => $action['value'],
                            'sent_at' => null,
                        ]);

                        $conversation->forceFill([
                            'last_message_at' => now(),
                            'status' => 'open',
                        ])->save();

                        DispatchDomainJob::dispatch(new SendWhatsAppMessageJob($outboundMessage->getKey()));
                        $appliedActions[] = 'send_whatsapp_message';
                    }
                }

                $run->update([
                    'result' => [
                        'matched' => true,
                        'applied_actions' => $appliedActions,
                    ],
                ]);
            } catch (Throwable $exception) {
                $run->update([
                    'status' => 'failed',
                    'error_message' => $exception->getMessage(),
                ]);
            }
        }
    }

    private function matchesConditions(AutomationRule $rule, Conversation $conversation, ?Message $message): bool
    {
        $conditions = $rule->conditions ?? [];

        if (($conditions['channel'] ?? null) && $conversation->channel !== $conditions['channel']) {
            return false;
        }

        if (($conditions['message_contains'] ?? null) && ! str_contains(mb_strtolower((string) $message?->body), mb_strtolower((string) $conditions['message_contains']))) {
            return false;
        }

        return true;
    }

    private function alreadySentWhatsAppMessage(AutomationRule $rule, Conversation $conversation): bool
    {
        return AutomationRun::query()
            ->where('automation_rule_id', $rule->getKey())
            ->where('context->conversation_id', $conversation->getKey())
            ->whereJsonContains('result->applied_actions', 'send_whatsapp_message')
            ->exists();
    }
}
