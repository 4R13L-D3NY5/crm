<?php

namespace App\Modules\Conversations\Actions;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Support\Facades\DB;

class CloseTicketAction
{
    public function execute(User $user, Conversation $conversation, array $payload = []): Conversation
    {
        return DB::transaction(function () use ($user, $conversation, $payload) {
            $conversation->update([
                'status' => 'closed',
                'closed_at' => now(),
                'rating' => $payload['rating'] ?? null,
                'feedback' => $payload['feedback'] ?? null,
            ]);

            // Si no se asignó calificación manual y send_survey no es falso (por defecto true), despachar encuesta CSAT por WhatsApp
            if (empty($payload['rating']) && ($payload['send_survey'] ?? true)) {
                try {
                    app(\App\Modules\Conversations\Services\CsatSurveyService::class)->dispatchSurvey($conversation, $user);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error('Error al despachar encuesta CSAT en CloseTicketAction: ' . $e->getMessage());
                }
            }

            $fresh = $conversation->fresh(['queue', 'assignee', 'contact', 'company']);
            \App\Modules\Conversations\Events\TicketUpdatedEvent::dispatch($fresh);

            return $fresh;
        });
    }
}
