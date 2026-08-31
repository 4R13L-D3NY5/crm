<?php

namespace App\Modules\AiAgent\Actions;

use App\Models\User;
use App\Modules\AiAgent\Models\AiAgent;
use App\Modules\AiAgent\Models\AiAgentRun;
use App\Modules\AiAgent\Services\AiProvider;
use App\Modules\Conversations\Models\Conversation;
use Throwable;

class GenerateReplySuggestionAction
{
    public function __construct(
        private readonly AiProvider $provider,
    ) {
    }

    public function execute(User $user, Conversation $conversation): AiAgentRun
    {
        $agent = AiAgent::query()->firstOrCreate(
            [
                'organization_id' => $user->current_organization_id,
                'name' => 'CRM Reply Assistant',
            ],
            [
                'provider' => 'fake',
                'system_prompt' => 'Sugiere una respuesta breve, amable y orientada a negocio.',
                'is_active' => true,
            ],
        );

        $conversation->loadMissing('messages.user', 'contact', 'company');

        $prompt = $agent->system_prompt ?: 'Sugiere una respuesta breve y profesional.';
        $inputSummary = $this->buildInputSummary($conversation);

        $run = AiAgentRun::query()->create([
            'organization_id' => $user->current_organization_id,
            'ai_agent_id' => $agent->getKey(),
            'conversation_id' => $conversation->getKey(),
            'triggered_by_user_id' => $user->getKey(),
            'run_type' => 'reply_suggestion',
            'status' => 'completed',
            'prompt' => $prompt,
            'input_summary' => $inputSummary,
            'output_text' => null,
        ]);

        try {
            $suggestion = $this->provider->generateReplySuggestion($prompt, $inputSummary);

            $run->update([
                'output_text' => $suggestion,
                'status' => 'completed',
            ]);
        } catch (Throwable $exception) {
            $run->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);
        }

        return $run->fresh();
    }

    private function buildInputSummary(Conversation $conversation): string
    {
        $lines = [
            'Canal: '.$conversation->channel,
            'Estado: '.$conversation->status,
            'Contacto: '.($conversation->contact?->name ?? 'Sin contacto'),
            'Empresa: '.($conversation->company?->name ?? 'Sin empresa'),
            'Mensajes recientes:',
        ];

        foreach ($conversation->messages->take(-6) as $message) {
            $author = $message->user?->name ?? match ($message->direction) {
                'inbound' => 'Cliente',
                'outbound' => 'Agente',
                default => 'Equipo',
            };

            $lines[] = "{$author}: {$message->body}";
        }

        return implode("\n", $lines);
    }
}
