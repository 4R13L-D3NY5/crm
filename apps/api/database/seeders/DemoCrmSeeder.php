<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\AiAgent\Models\AiAgent;
use App\Modules\AiAgent\Models\AiAgentRun;
use App\Modules\Automations\Models\AutomationRule;
use App\Modules\Automations\Models\AutomationRun;
use App\Modules\Companies\Models\Company;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use App\Modules\Conversations\Models\Message;
use App\Modules\Deals\Models\Deal;
use App\Modules\Deals\Models\DealStageHistory;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use App\Modules\WhatsApp\Models\WhatsAppWebhookEvent;
use App\Shared\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoCrmSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->updateOrCreate(
            ['slug' => 'crm-demo'],
            ['name' => 'CRM Demo'],
        );

        $secondaryOrganization = Organization::query()->updateOrCreate(
            ['slug' => 'crm-andina'],
            ['name' => 'CRM Andina'],
        );

        $users = $this->seedUsers($organization, $secondaryOrganization);
        $stages = $this->seedPipeline($organization);
        $tags = $this->seedTags($organization);
        [$contacts, $companies] = $this->seedContactsAndCompanies($organization, $tags);
        $deals = $this->seedDeals($organization, $stages, $contacts, $companies, $users);
        $conversations = $this->seedConversations($organization, $contacts, $companies, $users);
        $account = $this->seedWhatsApp($organization, $contacts, $conversations);
        $this->seedAutomations($organization, $users, $conversations);
        $this->seedAiAgent($organization, $users, $conversations);
        $this->seedAuditLogs($organization, $users, $conversations, $deals, $account);
        $this->seedSecondaryOrganizationData($secondaryOrganization, $users);
    }

    private function seedUsers(Organization $organization, Organization $secondaryOrganization): array
    {
        $users = [
            'owner' => User::query()->updateOrCreate(
                ['email' => 'admin@crm.local'],
                ['name' => 'Administrador CRM', 'password' => 'password'],
            ),
            'admin' => User::query()->updateOrCreate(
                ['email' => 'supervisora@crm.local'],
                ['name' => 'Lucia Herrera', 'password' => 'password'],
            ),
            'agent' => User::query()->updateOrCreate(
                ['email' => 'agente@crm.local'],
                ['name' => 'Mateo Rojas', 'password' => 'password'],
            ),
            'member' => User::query()->updateOrCreate(
                ['email' => 'analista@crm.local'],
                ['name' => 'Camila Vega', 'password' => 'password'],
            ),
            'viewer' => User::query()->updateOrCreate(
                ['email' => 'viewer@crm.local'],
                ['name' => 'Bruno Flores', 'password' => 'password'],
            ),
        ];

        foreach ([
            'owner' => 'owner',
            'admin' => 'admin',
            'agent' => 'agent',
            'member' => 'member',
            'viewer' => 'viewer',
        ] as $key => $role) {
            $this->attachMembership($organization, $users[$key], $role);
        }

        $this->attachMembership($secondaryOrganization, $users['owner'], 'owner');
        $this->attachMembership($secondaryOrganization, $users['agent'], 'agent');

        foreach ($users as $user) {
            $user->forceFill([
                'current_organization_id' => $organization->getKey(),
            ])->save();
        }

        return $users;
    }

    private function seedPipeline(Organization $organization): array
    {
        $pipeline = Pipeline::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'name' => 'Ventas CRM'],
            ['is_default' => true],
        );

        $stages = [];

        foreach ([
            'Nuevo' => ['position' => 1, 'probability' => 10, 'color' => '#3B6EA8'],
            'Contactado' => ['position' => 2, 'probability' => 30, 'color' => '#2F7D62'],
            'Propuesta' => ['position' => 3, 'probability' => 60, 'color' => '#BB8744'],
            'Ganado' => ['position' => 4, 'probability' => 100, 'color' => '#2F7D62'],
            'Perdido' => ['position' => 5, 'probability' => 0, 'color' => '#B94D3F'],
        ] as $name => $data) {
            $stages[$name] = PipelineStage::query()->updateOrCreate(
                ['pipeline_id' => $pipeline->getKey(), 'name' => $name],
                $data,
            );
        }

        return $stages;
    }

    private function seedTags(Organization $organization): array
    {
        $tags = [];

        foreach ([
            'Vip' => 'vip',
            'Renovacion' => 'renovacion',
            'Inbound' => 'inbound',
            'WhatsApp' => 'whatsapp',
            'Enterprise' => 'enterprise',
        ] as $name => $slug) {
            $tags[$slug] = Tag::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'slug' => $slug],
                ['name' => $name],
            );
        }

        return $tags;
    }

    private function seedContactsAndCompanies(Organization $organization, array $tags): array
    {
        $companies = collect([
            'Nova Industrial' => Company::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'name' => 'Nova Industrial'],
                ['industry' => 'Manufactura', 'website' => 'https://nova-industrial.demo', 'email' => 'contacto@nova-industrial.demo', 'phone' => '+59122100001', 'status' => 'active', 'notes' => 'Cliente enterprise con oportunidades abiertas.'],
            ),
            'Clinica Horizonte' => Company::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'name' => 'Clinica Horizonte'],
                ['industry' => 'Salud', 'website' => 'https://clinica-horizonte.demo', 'email' => 'ventas@clinica-horizonte.demo', 'phone' => '+59122100002', 'status' => 'lead', 'notes' => 'Prospecto con interes en varias sedes.'],
            ),
            'Grupo Altamar' => Company::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'name' => 'Grupo Altamar'],
                ['industry' => 'Retail', 'website' => 'https://grupo-altamar.demo', 'email' => 'expansion@grupo-altamar.demo', 'phone' => '+59122100003', 'status' => 'active', 'notes' => 'Cuenta con conversaciones activas por WhatsApp.'],
            ),
            'Andes Logistics' => Company::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'name' => 'Andes Logistics'],
                ['industry' => 'Servicios', 'website' => 'https://andes-logistics.demo', 'email' => 'compras@andes-logistics.demo', 'phone' => '+59122100004', 'status' => 'inactive', 'notes' => 'Cuenta pausada por presupuesto.'],
            ),
        ]);

        $contacts = collect([
            ['Carlos', 'Mendoza', 'carlos.mendoza@nova-industrial.demo', '59171122334', 'active', 'Director comercial. Prefiere WhatsApp.', 'Nova Industrial', ['vip', 'whatsapp']],
            ['Ana', 'Quiroga', 'ana.quiroga@clinica-horizonte.demo', '59172233445', 'lead', 'Solicito propuesta para tres sucursales.', 'Clinica Horizonte', ['inbound']],
            ['Luis', 'Salvatierra', 'luis.salvatierra@grupo-altamar.demo', '59173344556', 'active', 'Coordina aprobaciones internas.', 'Grupo Altamar', ['enterprise', 'whatsapp']],
            ['Mariela', 'Paz', 'mariela.paz@andes-logistics.demo', '59174455667', 'inactive', 'Cuenta en pausa por reestructuracion.', 'Andes Logistics', ['renovacion']],
            ['Javier', 'Suarez', 'javier.suarez@nova-industrial.demo', '59175566778', 'lead', 'Interesado en piloto de 30 dias.', 'Nova Industrial', ['inbound']],
            ['Paola', 'Rivera', 'paola.rivera@grupo-altamar.demo', '59176677889', 'active', 'Lidera operaciones y seguimiento.', 'Grupo Altamar', ['vip', 'enterprise']],
            ['Diego', 'Arias', 'diego.arias@clinica-horizonte.demo', '59177788990', 'lead', 'Solicito demo para su equipo.', 'Clinica Horizonte', ['whatsapp']],
            ['Sofia', 'Vargas', 'sofia.vargas@prospecto.demo', '59178899001', 'active', 'Lead directo del formulario web.', null, ['inbound']],
        ])->mapWithKeys(function (array $row) use ($organization, $companies, $tags) {
            [$firstName, $lastName, $email, $phone, $status, $notes, $companyName, $tagSlugs] = $row;

            $contact = Contact::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'email' => $email],
                ['first_name' => $firstName, 'last_name' => $lastName, 'phone' => $phone, 'status' => $status, 'notes' => $notes],
            );

            if ($companyName) {
                $companies[$companyName]->contacts()->syncWithoutDetaching([$contact->getKey()]);
            }

            $contact->tags()->syncWithoutDetaching(
                collect($tagSlugs)->map(fn (string $slug) => $tags[$slug]->getKey())->all(),
            );

            return [$email => $contact];
        });

        return [$contacts, $companies];
    }

    private function seedDeals(Organization $organization, array $stages, Collection $contacts, Collection $companies, array $users): Collection
    {
        return collect([
            ['Licencias corporativas Nova 2026', 'open', 18500, 'Propuesta', 'carlos.mendoza@nova-industrial.demo', 'Nova Industrial', 'Propuesta enviada y en revision financiera.', ['Nuevo', 'Contactado', 'Propuesta'], $users['admin']],
            ['Implementacion Clinica Horizonte', 'open', 9600, 'Contactado', 'ana.quiroga@clinica-horizonte.demo', 'Clinica Horizonte', 'Esperando reunion con direccion medica.', ['Nuevo', 'Contactado'], $users['agent']],
            ['Expansion omnicanal Altamar', 'won', 24300, 'Ganado', 'luis.salvatierra@grupo-altamar.demo', 'Grupo Altamar', 'Contrato firmado, onboarding en curso.', ['Nuevo', 'Contactado', 'Propuesta', 'Ganado'], $users['owner']],
            ['Reactivacion Andes Logistics', 'lost', 7200, 'Perdido', 'mariela.paz@andes-logistics.demo', 'Andes Logistics', 'Cuenta detenida por congelamiento presupuestario.', ['Nuevo', 'Contactado', 'Perdido'], $users['admin']],
            ['Piloto Nova sucursal sur', 'open', 4300, 'Nuevo', 'javier.suarez@nova-industrial.demo', 'Nova Industrial', 'Lead nuevo con interes por implementar rapido.', ['Nuevo'], $users['agent']],
            ['Cuenta inbound Sofia Vargas', 'open', 2800, 'Contactado', 'sofia.vargas@prospecto.demo', null, 'Prospecto individual captado desde formulario y WhatsApp.', ['Nuevo', 'Contactado'], $users['member']],
        ])->map(function (array $row) use ($organization, $stages, $contacts, $companies) {
            [$name, $status, $amount, $stageName, $contactEmail, $companyName, $notes, $history, $changedBy] = $row;
            $stage = $stages[$stageName];

            $deal = Deal::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'name' => $name],
                [
                    'pipeline_id' => $stage->pipeline_id,
                    'pipeline_stage_id' => $stage->getKey(),
                    'contact_id' => $contacts[$contactEmail]->getKey(),
                    'company_id' => $companyName ? $companies[$companyName]->getKey() : null,
                    'status' => $status,
                    'amount' => $amount,
                    'probability' => $stage->probability,
                    'expected_close_date' => now()->addDays(18)->toDateString(),
                    'notes' => $notes,
                ],
            );

            foreach ($history as $index => $historyStageName) {
                DealStageHistory::query()->firstOrCreate(
                    ['deal_id' => $deal->getKey(), 'to_stage_id' => $stages[$historyStageName]->getKey()],
                    [
                        'from_stage_id' => $index > 0 ? $stages[$history[$index - 1]]->getKey() : null,
                        'changed_by_user_id' => $changedBy->getKey(),
                        'created_at' => now()->subDays(count($history) - $index),
                        'updated_at' => now()->subDays(count($history) - $index),
                    ],
                );
            }

            return $deal;
        })->keyBy('name');
    }

    private function seedConversations(Organization $organization, Collection $contacts, Collection $companies, array $users): Collection
    {
        $baseTime = CarbonImmutable::now()->subHours(14);

        $definitions = [
            'Onboarding Nova Industrial' => ['manual', 'open', 'carlos.mendoza@nova-industrial.demo', 'Nova Industrial', $users['admin'], $users['agent'], $users['admin'], [
                ['internal', $users['admin'], 'sent', 'Abrimos seguimiento para coordinar onboarding con el equipo de Nova.', 780],
                ['internal', $users['agent'], 'sent', 'Agenda propuesta para la proxima semana enviada al cliente.', 720],
            ]],
            'Consulta Clinica Horizonte por tres sedes' => ['manual', 'pending', 'ana.quiroga@clinica-horizonte.demo', 'Clinica Horizonte', $users['agent'], $users['member'], $users['agent'], [
                ['internal', $users['agent'], 'sent', 'Cliente pide comparativo de planes y tiempos de despliegue.', 650],
                ['internal', $users['member'], 'sent', 'Preparando el material de propuesta y plan de capacitacion.', 560],
            ]],
            'WhatsApp: Carlos Mendoza' => ['whatsapp', 'open', 'carlos.mendoza@nova-industrial.demo', 'Nova Industrial', null, $users['agent'], $users['admin'], [
                ['inbound', null, 'received', 'Hola, me confirman si la propuesta incluye soporte regional?', 320],
                ['outbound', $users['agent'], 'sent', 'Si, incluye soporte regional y acompanamiento durante el despliegue.', 300],
                ['inbound', null, 'received', 'Perfecto, entonces avancemos con la validacion interna.', 280],
            ]],
            'WhatsApp: Luis Salvatierra' => ['whatsapp', 'resolved', 'luis.salvatierra@grupo-altamar.demo', 'Grupo Altamar', null, $users['admin'], $users['owner'], [
                ['inbound', null, 'received', 'Gracias por el apoyo, el contrato ya esta firmado.', 220],
                ['outbound', $users['admin'], 'sent', 'Excelente noticia. Dejamos la conversacion resuelta y seguimos con onboarding.', 200],
            ]],
            'WhatsApp: Sofia Vargas' => ['whatsapp', 'pending', 'sofia.vargas@prospecto.demo', null, null, $users['member'], $users['agent'], [
                ['inbound', null, 'received', 'Buen dia, quisiera saber si tienen plan para equipos pequenos.', 150],
                ['outbound', $users['member'], 'failed', 'Claro, tenemos un plan inicial flexible y rapido de activar.', 120, 'Meta API timeout'],
            ]],
        ];

        $conversations = collect($definitions)->mapWithKeys(function (array $data, string $subject) use ($organization, $contacts, $companies, $baseTime) {
            [$channel, $status, $contactEmail, $companyName, $createdBy, , , $messages] = $data;
            $latestMinutes = min(array_column($messages, 4));
            $lastMessageAt = $baseTime->addMinutes(900 - $latestMinutes);

            $conversation = Conversation::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'subject' => $subject],
                [
                    'contact_id' => $contacts[$contactEmail]->getKey(),
                    'company_id' => $companyName ? $companies[$companyName]->getKey() : null,
                    'created_by_user_id' => $createdBy?->getKey(),
                    'channel' => $channel,
                    'status' => $status,
                    'last_message_at' => $lastMessageAt,
                ],
            );

            return [$subject => $conversation];
        });

        foreach ($definitions as $subject => $data) {
            [, , , , , $assignedTo, $assignedBy, $messages] = $data;
            $conversation = $conversations[$subject];

            ConversationAssignment::query()->updateOrCreate(
                ['conversation_id' => $conversation->getKey()],
                ['assigned_to_user_id' => $assignedTo->getKey(), 'assigned_by_user_id' => $assignedBy->getKey()],
            );

            foreach ($messages as $messageData) {
                Message::query()->firstOrCreate(
                    ['conversation_id' => $conversation->getKey(), 'direction' => $messageData[0], 'body' => $messageData[3]],
                    [
                        'organization_id' => $organization->getKey(),
                        'user_id' => $messageData[1]?->getKey(),
                        'message_type' => 'text',
                        'message_status' => $messageData[2],
                        'error_message' => $messageData[5] ?? null,
                        'sent_at' => CarbonImmutable::now()->subMinutes($messageData[4]),
                        'created_at' => CarbonImmutable::now()->subMinutes($messageData[4]),
                        'updated_at' => CarbonImmutable::now()->subMinutes($messageData[4]),
                    ],
                );
            }
        }

        return $conversations;
    }

    private function seedWhatsApp(Organization $organization, Collection $contacts, Collection $conversations): WhatsAppAccount
    {
        $account = WhatsAppAccount::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'name' => 'WhatsApp Demo'],
            [
                'phone_number_id' => '123456789012345',
                'display_phone_number' => '+591 7000-0001',
                'business_account_id' => '987654321012345',
                'verify_token' => 'crm-meta-verify-2026',
                'access_token' => 'meta-access-token-demo',
                'is_active' => true,
            ],
        );

        $messageCarlosInbound = Message::query()->where('conversation_id', $conversations['WhatsApp: Carlos Mendoza']->getKey())->where('direction', 'inbound')->oldest('created_at')->first();
        $messageCarlosOutbound = Message::query()->where('conversation_id', $conversations['WhatsApp: Carlos Mendoza']->getKey())->where('direction', 'outbound')->first();
        $messageSofiaFailed = Message::query()->where('conversation_id', $conversations['WhatsApp: Sofia Vargas']->getKey())->where('direction', 'outbound')->first();

        foreach ([
            ['wamid.demo.carlos.in.1', $contacts['carlos.mendoza@nova-industrial.demo']->getKey(), $conversations['WhatsApp: Carlos Mendoza']->getKey(), $messageCarlosInbound?->getKey(), 'inbound', 'received', '59171122334', ['type' => 'text', 'from' => '59171122334']],
            ['wamid.demo.carlos.out.1', $contacts['carlos.mendoza@nova-industrial.demo']->getKey(), $conversations['WhatsApp: Carlos Mendoza']->getKey(), $messageCarlosOutbound?->getKey(), 'outbound', 'sent', '59171122334', ['messages' => [['id' => 'wamid.demo.carlos.out.1']]]],
            ['wamid.demo.sofia.out.1', $contacts['sofia.vargas@prospecto.demo']->getKey(), $conversations['WhatsApp: Sofia Vargas']->getKey(), $messageSofiaFailed?->getKey(), 'outbound', 'failed', '59178899001', ['errors' => [['title' => 'Meta API timeout']]]],
        ] as [$providerId, $contactId, $conversationId, $messageId, $direction, $status, $fromPhone, $payload]) {
            WhatsAppMessageMapping::query()->updateOrCreate(
                ['provider_message_id' => $providerId],
                [
                    'organization_id' => $organization->getKey(),
                    'whatsapp_account_id' => $account->getKey(),
                    'contact_id' => $contactId,
                    'conversation_id' => $conversationId,
                    'message_id' => $messageId,
                    'direction' => $direction,
                    'status' => $status,
                    'from_phone' => $fromPhone,
                    'to_phone_number_id' => $account->phone_number_id,
                    'payload' => $payload,
                ],
            );
        }

        foreach ([
            ['messages', 'processed', null, '59171122334', 'Hola, me confirman si la propuesta incluye soporte regional?', 6],
            ['messages', 'pending', null, '59178899001', 'Buen dia, quisiera saber si tienen plan para equipos pequenos.', 4],
            ['message_status', 'failed', 'No se pudo resolver la cuenta para el payload recibido.', '59170000000', 'Evento fallido de demostracion.', 2],
        ] as [$eventType, $processingStatus, $errorMessage, $fromPhone, $body, $hoursAgo]) {
            WhatsAppWebhookEvent::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'processing_status' => $processingStatus, 'created_at' => now()->subHours($hoursAgo)],
                [
                    'whatsapp_account_id' => $account->getKey(),
                    'event_type' => $eventType,
                    'payload' => ['entry' => [[ 'changes' => [[ 'field' => 'messages', 'value' => ['metadata' => ['phone_number_id' => $account->phone_number_id], 'messages' => [[ 'from' => $fromPhone, 'text' => ['body' => $body] ]]], ]]]]],
                    'headers' => ['content-type' => ['application/json']],
                    'processed_at' => $processingStatus === 'pending' ? null : now()->subHours($hoursAgo)->addMinutes(2),
                    'error_message' => $errorMessage,
                    'updated_at' => now()->subHours($hoursAgo),
                ],
            );
        }

        return $account;
    }

    private function seedAutomations(Organization $organization, array $users, Collection $conversations): void
    {
        $assignRule = AutomationRule::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'name' => 'Asignar inbound WhatsApp a Mateo'],
            ['trigger_type' => 'message.inbound.received', 'conditions' => ['channel' => 'whatsapp'], 'actions' => [['type' => 'assign_user', 'value' => $users['agent']->getKey()], ['type' => 'set_status', 'value' => 'pending']], 'is_active' => true],
        );

        $manualRule = AutomationRule::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'name' => 'Marcar conversaciones manuales como pendientes'],
            ['trigger_type' => 'conversation.created', 'conditions' => ['channel' => 'manual'], 'actions' => [['type' => 'set_status', 'value' => 'pending']], 'is_active' => true],
        );

        AutomationRun::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'automation_rule_id' => $assignRule->getKey(), 'trigger_type' => 'message.inbound.received', 'created_at' => now()->subHours(4)],
            ['status' => 'completed', 'context' => ['channel' => 'whatsapp', 'conversation_id' => $conversations['WhatsApp: Carlos Mendoza']->getKey()], 'result' => ['matched' => true, 'applied_actions' => ['assign_user', 'set_status']], 'updated_at' => now()->subHours(4)],
        );

        AutomationRun::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'automation_rule_id' => $manualRule->getKey(), 'trigger_type' => 'conversation.created', 'created_at' => now()->subHours(2)],
            ['status' => 'failed', 'context' => ['channel' => 'manual', 'conversation_id' => $conversations['Consulta Clinica Horizonte por tres sedes']->getKey()], 'result' => ['matched' => true], 'error_message' => 'No se pudo notificar al equipo interno en esta simulacion.', 'updated_at' => now()->subHours(2)],
        );
    }

    private function seedAiAgent(Organization $organization, array $users, Collection $conversations): void
    {
        $agent = AiAgent::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'name' => 'CRM Reply Assistant'],
            ['provider' => 'fake', 'system_prompt' => 'Sugiere respuestas claras, breves y orientadas a negocio.', 'is_active' => true],
        );

        AiAgentRun::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'ai_agent_id' => $agent->getKey(), 'conversation_id' => $conversations['WhatsApp: Carlos Mendoza']->getKey()],
            ['triggered_by_user_id' => $users['agent']->getKey(), 'run_type' => 'reply_suggestion', 'status' => 'completed', 'prompt' => 'Sugiere una respuesta amable sobre soporte regional.', 'input_summary' => 'Cliente pregunta si la propuesta incluye soporte regional y seguimiento de despliegue.', 'output_text' => 'Claro, la propuesta contempla soporte regional y acompanamiento cercano durante todo el despliegue. Si quieres, hoy mismo te comparto el detalle operativo.', 'error_message' => null],
        );
    }

    private function seedAuditLogs(Organization $organization, array $users, Collection $conversations, Collection $deals, WhatsAppAccount $account): void
    {
        foreach ([
            ['auth.login', $users['owner'], null, null, ['guard' => 'web'], 440],
            ['tenancy.organization_switched', $users['owner'], Organization::class, $organization->getKey(), ['organization_name' => $organization->name], 400],
            ['conversations.created', $users['admin'], Conversation::class, $conversations['Onboarding Nova Industrial']->getKey(), ['channel' => 'manual'], 360],
            ['conversations.assigned', $users['admin'], Conversation::class, $conversations['WhatsApp: Carlos Mendoza']->getKey(), ['assigned_to' => $users['agent']->email], 250],
            ['whatsapp.webhook_received', null, WhatsAppAccount::class, $account->getKey(), ['phone_number_id' => $account->phone_number_id], 230],
            ['whatsapp.message_queued', $users['member'], Conversation::class, $conversations['WhatsApp: Sofia Vargas']->getKey(), ['status' => 'pending'], 140],
            ['whatsapp.message_retry_requested', $users['member'], Conversation::class, $conversations['WhatsApp: Sofia Vargas']->getKey(), ['status' => 'failed'], 110],
            ['deals.stage_changed', $users['owner'], Deal::class, $deals['Expansion omnicanal Altamar']->getKey(), ['to_stage' => 'Ganado'], 90],
        ] as [$event, $user, $auditableType, $auditableId, $metadata, $minutesAgo]) {
            AuditLog::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'event' => $event, 'created_at' => now()->subMinutes($minutesAgo)],
                ['user_id' => $user?->getKey(), 'auditable_type' => $auditableType, 'auditable_id' => $auditableId, 'metadata' => $metadata, 'ip_address' => '127.0.0.1', 'user_agent' => 'Demo CRM Seeder', 'updated_at' => now()->subMinutes($minutesAgo)],
            );
        }
    }

    private function seedSecondaryOrganizationData(Organization $organization, array $users): void
    {
        $pipeline = Pipeline::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'name' => 'Expansion Andina'],
            ['is_default' => true],
        );

        PipelineStage::query()->updateOrCreate(
            ['pipeline_id' => $pipeline->getKey(), 'name' => 'Nuevo'],
            ['position' => 1, 'probability' => 15, 'color' => '#3B6EA8'],
        );

        $contact = Contact::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'email' => 'contacto@andina.demo'],
            ['first_name' => 'Patricia', 'last_name' => 'Mora', 'phone' => '59179900112', 'status' => 'lead', 'notes' => 'Lead de la segunda organizacion para probar cambio de contexto.'],
        );

        Conversation::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'subject' => 'Bienvenida CRM Andina'],
            ['contact_id' => $contact->getKey(), 'created_by_user_id' => $users['owner']->getKey(), 'channel' => 'manual', 'status' => 'open', 'last_message_at' => now()->subDay()],
        );
    }

    private function attachMembership(Organization $organization, User $user, string $role): void
    {
        $organization->users()->syncWithoutDetaching([
            $user->getKey() => [
                'id' => (string) str()->ulid(),
                'role' => $role,
            ],
        ]);
    }
}
