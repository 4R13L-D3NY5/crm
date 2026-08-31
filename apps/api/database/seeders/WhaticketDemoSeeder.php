<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Models\AiKnowledgeBase;
use App\Modules\HentleAi\Models\AiKnowledgeChunk;
use App\Modules\Queues\Models\BusinessHour;
use App\Modules\Queues\Models\Queue;
use App\Modules\QuickMessages\Models\QuickMessage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WhaticketDemoSeeder extends Seeder
{
    public function run(): void
    {
        $organization = Organization::query()->first();
        $user = User::query()->first();

        if (!$organization || !$user) {
            return;
        }

        $orgId = $organization->id;
        $userId = $user->id;

        // 1. Filas de Atención (Queues)
        $queueSales = Queue::updateOrCreate(
            ['organization_id' => $orgId, 'name' => 'Ventas y Cotizaciones'],
            [
                'color' => '#25D366',
                'greeting_message' => '¡Hola! Gracias por comunicarte con el área de Ventas. ¿En qué producto o servicio estás interesado?',
                'out_of_hours_message' => 'Actualmente estamos fuera de horario comercial. Dejános tu consulta y te responderemos a primera hora.',
                'order_index' => 1,
                'is_active' => true,
            ]
        );
        $queueSales->users()->sync([$userId]);

        $queueSupport = Queue::updateOrCreate(
            ['organization_id' => $orgId, 'name' => 'Soporte Técnico'],
            [
                'color' => '#0088CC',
                'greeting_message' => 'Bienvenido a Soporte Técnico. Describe el requerimiento o adjunta capturas para asistirte rápidamente.',
                'out_of_hours_message' => 'El equipo de soporte atiende de 08:30 a 18:30. Tu ticket ha sido registrado.',
                'order_index' => 2,
                'is_active' => true,
            ]
        );
        $queueSupport->users()->sync([$userId]);

        $queueBilling = Queue::updateOrCreate(
            ['organization_id' => $orgId, 'name' => 'Facturación y Pagos'],
            [
                'color' => '#F59E0B',
                'greeting_message' => 'Área de Facturación. Por favor indícanos tu número de NIT/RUT o adjunta tu comprobante.',
                'order_index' => 3,
                'is_active' => true,
            ]
        );
        $queueBilling->users()->sync([$userId]);

        // 2. Horarios Comerciales (Lunes a Viernes)
        for ($day = 1; $day <= 5; $day++) {
            BusinessHour::updateOrCreate(
                ['organization_id' => $orgId, 'day_of_week' => $day],
                [
                    'open_time_1' => '08:30:00',
                    'close_time_1' => '12:30:00',
                    'open_time_2' => '14:00:00',
                    'close_time_2' => '18:30:00',
                    'is_closed' => false,
                ]
            );
        }

        // 3. Respuestas Rápidas (Quick Messages / Atajos)
        $quickMessages = [
            [
                'shortcut' => 'bienvenida',
                'message' => "¡Hola! Qué gusto saludarte. Mi nombre es {$user->name}, asesor de atención. ¿En qué te puedo ayudar el día de hoy?",
            ],
            [
                'shortcut' => 'horarios',
                'message' => "Nuestro horario de atención oficial es de Lunes a Viernes de 08:30 a 18:30 y Sábados de 09:00 a 13:00 (GMT-4).",
            ],
            [
                'shortcut' => 'precios',
                'message' => "Puedes consultar nuestros paquetes y planes vigentes directamente en: https://xpertiflow.bo/planes",
            ],
            [
                'shortcut' => 'transferencia',
                'message' => "Voy a transferir tu consulta al departamento correspondiente para que un especialista continúe tu atención. Permíteme un momento por favor.",
            ],
            [
                'shortcut' => 'despedida',
                'message' => "¡Muchas gracias por comunicarte con nosotros! Si necesitas algo más, seguimos a tu disposición. ¡Que tengas un excelente día!",
            ],
        ];

        foreach ($quickMessages as $qm) {
            QuickMessage::updateOrCreate(
                ['organization_id' => $orgId, 'shortcut' => $qm['shortcut']],
                [
                    'user_id' => null,
                    'message' => $qm['message'],
                    'is_general' => true,
                ]
            );
        }

        // 4. Contactos y Tickets de Demostración en las 3 Pestañas
        $contact1 = Contact::firstOrCreate(
            ['organization_id' => $orgId, 'phone' => '+59177889901'],
            ['first_name' => 'Carlos', 'last_name' => 'Mendoza', 'email' => 'carlos.mendoza@demo.com']
        );

        $contact2 = Contact::firstOrCreate(
            ['organization_id' => $orgId, 'phone' => '+59171234567'],
            ['first_name' => 'Mariana', 'last_name' => 'Rojas', 'email' => 'mariana.rojas@demo.com']
        );

        $contact3 = Contact::firstOrCreate(
            ['organization_id' => $orgId, 'phone' => '+59176543210'],
            ['first_name' => 'Rodrigo', 'last_name' => 'Paredes', 'email' => 'rodrigo.paredes@demo.com']
        );

        // Ticket 1: ATENDIENDO (Open, asignado a mí)
        $ticketAttending = Conversation::create([
            'organization_id' => $orgId,
            'contact_id' => $contact1->id,
            'queue_id' => $queueSales->id,
            'created_by_user_id' => $userId,
            'assigned_to_user_id' => $userId,
            'channel' => 'whatsapp',
            'status' => 'open',
            'subject' => 'Consulta plan empresarial',
            'last_message_at' => now()->subMinutes(5),
        ]);

        ConversationAssignment::updateOrCreate(
            ['conversation_id' => $ticketAttending->id],
            ['assigned_to_user_id' => $userId, 'assigned_by_user_id' => $userId]
        );

        Message::create([
            'organization_id' => $orgId,
            'conversation_id' => $ticketAttending->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Hola, buenas tardes. Quería cotizar el servicio para 10 agentes de atención por WhatsApp.',
            'sent_at' => now()->subMinutes(15),
        ]);

        Message::create([
            'organization_id' => $orgId,
            'conversation_id' => $ticketAttending->id,
            'user_id' => $userId,
            'direction' => 'internal',
            'is_internal' => true,
            'message_type' => 'text',
            'body' => '🔒 Nota interna: Cliente calificado. Empresa de distribución con interés en módulo de campañas masivas.',
            'sent_at' => now()->subMinutes(10),
        ]);

        Message::create([
            'organization_id' => $orgId,
            'conversation_id' => $ticketAttending->id,
            'user_id' => $userId,
            'direction' => 'outbound',
            'is_internal' => false,
            'delivery_status' => 'read',
            'message_type' => 'text',
            'body' => '¡Hola Carlos! Con gusto. Para 10 usuarios el plan recomendado es el Pro con conexión multi-número y bots RAG incluidos. ¿Te gustaría agendar una demo guiada?',
            'sent_at' => now()->subMinutes(5),
        ]);

        // Ticket 2: AGUARDANDO (Pending, sin agente asignado)
        $ticketPending = Conversation::create([
            'organization_id' => $orgId,
            'contact_id' => $contact2->id,
            'queue_id' => $queueSupport->id,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'unread_count' => 1,
            'subject' => 'Problema al sincronizar QR',
            'last_message_at' => now()->subMinutes(2),
        ]);

        Message::create([
            'organization_id' => $orgId,
            'conversation_id' => $ticketPending->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Hola soporte, me sale un error de timeout al intentar escanear el código QR desde mi celular.',
            'sent_at' => now()->subMinutes(2),
        ]);

        // Ticket 3: FINALIZADO (Closed, resuelto)
        $ticketClosed = Conversation::create([
            'organization_id' => $orgId,
            'contact_id' => $contact3->id,
            'queue_id' => $queueBilling->id,
            'assigned_to_user_id' => $userId,
            'channel' => 'whatsapp',
            'status' => 'closed',
            'subject' => 'Envío de factura electrónica',
            'last_message_at' => now()->subHours(3),
            'closed_at' => now()->subHours(2),
            'rating' => 5,
            'feedback' => 'Excelente y rápida atención.',
        ]);

        Message::create([
            'organization_id' => $orgId,
            'conversation_id' => $ticketClosed->id,
            'direction' => 'inbound',
            'message_type' => 'text',
            'body' => 'Buenas tardes, ¿me pueden enviar la factura del mes de agosto por favor?',
            'sent_at' => now()->subHours(3),
        ]);

        Message::create([
            'organization_id' => $orgId,
            'conversation_id' => $ticketClosed->id,
            'user_id' => $userId,
            'direction' => 'outbound',
            'is_internal' => false,
            'delivery_status' => 'read',
            'message_type' => 'text',
            'body' => 'Estimado Rodrigo, adjuntamos la factura en formato PDF. Ya quedó registrado en el sistema. ¡Gracias por tu pago!',
            'sent_at' => now()->subHours(2)->subMinutes(50),
        ]);

        // 5. Base de Conocimiento RAG para Hentle-AI
        $kb = AiKnowledgeBase::updateOrCreate(
            ['organization_id' => $orgId, 'name' => 'Base de Conocimiento XpertiFlow'],
            [
                'description' => 'Documentación general, catálogo de soluciones y políticas de servicio.',
                'provider' => 'gemini',
                'embedding_model' => 'text-embedding-004',
                'is_active' => true,
            ]
        );

        AiKnowledgeChunk::updateOrCreate(
            ['organization_id' => $orgId, 'knowledge_base_id' => $kb->id, 'title' => 'Planes y Licenciamiento'],
            [
                'content' => 'XpertiFlow CRM ofrece tres planes: Starter ($29/mes hasta 3 agentes), Pro ($79/mes hasta 10 agentes y campañas ilimitadas) y Enterprise ($199/mes con servidores dedicados, SLA 99.9% y copiloto Hentle-AI ilimitado).',
            ]
        );

        AiKnowledgeChunk::updateOrCreate(
            ['organization_id' => $orgId, 'knowledge_base_id' => $kb->id, 'title' => 'Requisitos para Conexión WhatsApp'],
            [
                'content' => 'Para conectar WhatsApp se puede usar Meta Cloud API oficial con número verificado o conexión rápida por escaneo de código QR mediante Evolution API.',
            ]
        );
    }
}
