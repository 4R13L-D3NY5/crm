<?php

namespace App\Modules\Conversations\Actions;

use App\Models\User;
use App\Modules\Automations\Jobs\RunAutomationRulesJob;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use App\Modules\Conversations\Models\Message;
use App\Modules\WhatsApp\Actions\SendWhatsAppMessageAction;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Shared\Support\DispatchDomainJob;

class CreateConversationAction
{
    public function execute(User $user, array $payload): Conversation
    {
        $contactId = $payload['contact_id'] ?? null;
        $phone = $payload['phone'] ?? null;

        // Si no se proporcionó contact_id pero sí un número de teléfono, buscar o crear el contacto
        if (empty($contactId) && !empty($phone)) {
            $normalizedPhone = preg_replace('/[^\d+]/', '', $phone);
            $rawName = !empty($payload['contact_name']) ? trim($payload['contact_name']) : $normalizedPhone;
            $nameParts = explode(' ', $rawName, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';

            $contact = Contact::query()->firstOrCreate(
                [
                    'organization_id' => $user->current_organization_id,
                    'phone' => $normalizedPhone,
                ],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'status' => 'lead',
                ]
            );
            $contactId = $contact->id;
        }

        $channel = $payload['channel'] ?? 'whatsapp';
        $whatsappAccountId = $payload['whatsapp_account_id'] ?? null;

        // Si no se pasó whatsapp_account_id pero es canal WhatsApp, asociar la cuenta activa
        if (empty($whatsappAccountId) && $channel === 'whatsapp') {
            $whatsappAccountId = WhatsAppAccount::query()
                ->where('organization_id', $user->current_organization_id)
                ->where('is_active', true)
                ->where('status', 'CONNECTED')
                ->latest('last_connected_at')
                ->value('id')
                ?? WhatsAppAccount::query()
                    ->where('organization_id', $user->current_organization_id)
                    ->where('is_active', true)
                    ->value('id');
        }

        $conversation = Conversation::query()->create([
            'organization_id' => $user->current_organization_id,
            'contact_id' => $contactId,
            'company_id' => $payload['company_id'] ?? null,
            'created_by_user_id' => $user->getKey(),
            'assigned_to_user_id' => $payload['assigned_to_user_id'] ?? $user->getKey(),
            'channel' => $channel,
            'whatsapp_account_id' => $whatsappAccountId,
            'status' => $payload['status'] ?? 'open',
            'subject' => $payload['subject'] ?? null,
            'last_message_at' => now(),
        ]);

        if (array_key_exists('assigned_to_user_id', $payload) || filled($conversation->assigned_to_user_id)) {
            ConversationAssignment::query()->create([
                'conversation_id' => $conversation->getKey(),
                'assigned_to_user_id' => $conversation->assigned_to_user_id,
                'assigned_by_user_id' => $user->getKey(),
            ]);
        }

        // Si es canal WhatsApp y hay un mensaje inicial, despacharlo a la cola de envío real de WhatsApp
        if ($channel === 'whatsapp' || !empty($whatsappAccountId)) {
            app(SendWhatsAppMessageAction::class)->execute(
                $user,
                $conversation->load('contact'),
                ['body' => $payload['message']]
            );
        } else {
            Message::query()->create([
                'organization_id' => $user->current_organization_id,
                'conversation_id' => $conversation->getKey(),
                'user_id' => $user->getKey(),
                'direction' => 'internal',
                'message_type' => 'text',
                'message_status' => 'sent',
                'body' => $payload['message'],
                'sent_at' => now(),
            ]);
        }

        DispatchDomainJob::dispatch(new RunAutomationRulesJob(
            $user->current_organization_id,
            'conversation.created',
            $conversation->getKey(),
        ));

        return $conversation->fresh()->load([
            'contact.tags',
            'company',
            'assignment.assignee',
            'latestMessage.user',
            'messages.user',
            'whatsappAccount',
        ]);
    }
}
