<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Parameters\Actions\DispatchOutOfHoursAutoReplyAction;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BusinessHoursAutoReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_out_of_hours_auto_reply_when_outside_business_hours(): void
    {
        Queue::fake([SendWhatsAppMessageJob::class]);

        $org = Organization::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $org->id,
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $config = BusinessHoursConfig::create([
            'organization_id' => $org->id,
            'name' => 'Horario Laboral General',
            'is_active' => true,
            'validity_type' => 'immediate',
            'timezone' => 'America/La_Paz',
            'schedule_days' => [
                'friday' => ['enabled' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
            ],
            'auto_reply_enabled' => true,
            'auto_reply_message' => '¡Hola! Gracias por comunicarte. Estamos fuera de horario.',
        ]);

        $contact = Contact::factory()->create(['organization_id' => $org->id, 'phone' => '59172228281']);
        $conversation = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'whatsapp_account_id' => $account->id,
            'status' => 'open',
        ]);

        // Simular hora fuera de horario: Viernes 20:00
        Carbon::setTestNow(Carbon::parse('2026-10-16 20:00:00', 'America/La_Paz'));

        $action = app(DispatchOutOfHoursAutoReplyAction::class);
        $reply = $action->execute($conversation);

        $this->assertNotNull($reply);
        $this->assertSame('¡Hola! Gracias por comunicarte. Estamos fuera de horario.', $reply->body);
        $this->assertSame('outbound', $reply->direction);

        Queue::assertPushed(SendWhatsAppMessageJob::class);

        // Anti-spam: si escribe de nuevo a las 20:00:30 (dentro de 1 minuto), no debe repetir
        Carbon::setTestNow(Carbon::parse('2026-10-16 20:00:30', 'America/La_Paz'));
        $reply2 = $action->execute($conversation);
        $this->assertNull($reply2);

        Carbon::setTestNow();
    }

    public function test_does_not_dispatch_auto_reply_during_business_hours(): void
    {
        Queue::fake([SendWhatsAppMessageJob::class]);

        $org = Organization::factory()->create();
        $config = BusinessHoursConfig::create([
            'organization_id' => $org->id,
            'name' => 'Horario Laboral General',
            'is_active' => true,
            'validity_type' => 'immediate',
            'timezone' => 'America/La_Paz',
            'schedule_days' => [
                'friday' => ['enabled' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
            ],
            'auto_reply_enabled' => true,
            'auto_reply_message' => '¡Hola! Estamos fuera de horario.',
        ]);

        $contact = Contact::factory()->create(['organization_id' => $org->id, 'phone' => '59172228282']);
        $conversation = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        // Simular hora dentro de horario: Viernes 10:30
        Carbon::setTestNow(Carbon::parse('2026-10-16 10:30:00', 'America/La_Paz'));

        $action = app(DispatchOutOfHoursAutoReplyAction::class);
        $reply = $action->execute($conversation);

        $this->assertNull($reply);
        Queue::assertNotPushed(SendWhatsAppMessageJob::class);

        Carbon::setTestNow();
    }

    public function test_baileys_inbound_triggers_auto_reply_when_outside_hours(): void
    {
        Queue::fake([SendWhatsAppMessageJob::class]);

        $org = Organization::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $org->id,
            'session_type' => 'baileys_qr',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        BusinessHoursConfig::create([
            'organization_id' => $org->id,
            'name' => 'Horario Laboral General',
            'is_active' => true,
            'validity_type' => 'immediate',
            'timezone' => 'America/La_Paz',
            'schedule_days' => [
                'friday' => ['enabled' => true, 'start_time' => '08:30', 'end_time' => '18:30'],
            ],
            'auto_reply_enabled' => true,
            'auto_reply_message' => 'Fuera de horario automático.',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-10-16 21:00:00', 'America/La_Paz'));

        $payload = [
            'account_id' => $account->id,
            'event' => 'message',
            'from_phone' => '59177788999',
            'from_name' => 'Postulante Nocturno',
            'body' => 'Hola, siguen atendiendo?',
            'provider_message_id' => 'baileys_night_' . time(),
            'from_me' => false,
        ];

        $response = $this->postJson('/api/whatsapp/baileys/webhook', $payload);
        $response->assertOk();

        // Verificar que se haya creado el mensaje outbound de auto-respuesta
        $this->assertDatabaseHas('messages', [
            'organization_id' => $org->id,
            'direction' => 'outbound',
            'body' => 'Fuera de horario automático.',
        ]);

        Queue::assertPushed(SendWhatsAppMessageJob::class);

        Carbon::setTestNow();
    }

    public function test_multi_slot_morning_and_afternoon_schedule(): void
    {
        $org = Organization::factory()->create();
        $config = BusinessHoursConfig::create([
            'organization_id' => $org->id,
            'name' => 'Horario con Turnos UNITEPC',
            'is_active' => true,
            'schedule_days' => [
                'monday' => [
                    'enabled' => true,
                    'start_time' => '07:45',
                    'end_time' => '18:30',
                    'slots' => [
                        ['name' => 'Mañana', 'start_time' => '07:45', 'end_time' => '12:30', 'enabled' => true],
                        ['name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '18:30', 'enabled' => true],
                    ],
                ],
            ],
            'auto_reply_enabled' => true,
        ]);

        // Lunes 09:00 -> En turno mañana -> Dentro de horario
        $m1 = Carbon::parse('2026-10-19 09:00:00', 'America/La_Paz');
        $this->assertTrue($config->isWithinHours($m1));

        // Lunes 13:15 -> Almuerzo (entre 12:30 y 14:00) -> Fuera de horario
        $m2 = Carbon::parse('2026-10-19 13:15:00', 'America/La_Paz');
        $this->assertFalse($config->isWithinHours($m2));

        // Lunes 16:00 -> En turno tarde -> Dentro de horario
        $m3 = Carbon::parse('2026-10-19 16:00:00', 'America/La_Paz');
        $this->assertTrue($config->isWithinHours($m3));

        // Lunes 19:00 -> Después del turno tarde -> Fuera de horario
        $m4 = Carbon::parse('2026-10-19 19:00:00', 'America/La_Paz');
        $this->assertFalse($config->isWithinHours($m4));
    }

    public function test_operator_specific_schedule_takes_precedence_for_assigned_conversation(): void
    {
        Queue::fake([SendWhatsAppMessageJob::class]);

        $org = Organization::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $org->id,
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $operator = User::factory()->create(['current_organization_id' => $org->id]);

        // 1. Horario General: Lunes de 07:45 a 18:30
        BusinessHoursConfig::create([
            'organization_id' => $org->id,
            'name' => 'Horario General',
            'scope_type' => 'general',
            'is_default' => true,
            'is_active' => true,
            'timezone' => 'America/La_Paz',
            'schedule_days' => [
                'monday' => ['enabled' => true, 'start_time' => '07:45', 'end_time' => '18:30'],
            ],
            'auto_reply_enabled' => true,
            'auto_reply_message' => 'Respuesta General.',
        ]);

        // 2. Horario Operador: Turno Nocturno de 18:00 a 22:00
        $opConfig = BusinessHoursConfig::create([
            'organization_id' => $org->id,
            'name' => 'Turno Nocturno Yerko',
            'scope_type' => 'operator',
            'is_active' => true,
            'timezone' => 'America/La_Paz',
            'schedule_days' => [
                'monday' => ['enabled' => true, 'start_time' => '18:00', 'end_time' => '22:00'],
            ],
            'auto_reply_enabled' => true,
            'auto_reply_message' => 'Yerko está fuera de su turno especial.',
        ]);
        $opConfig->users()->attach($operator->id);

        $contact = Contact::factory()->create(['organization_id' => $org->id]);
        $conversation = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'assigned_to_user_id' => $operator->id, // Asignado a Yerko
            'channel' => 'whatsapp',
            'whatsapp_account_id' => $account->id,
            'status' => 'open',
        ]);

        // A las 20:00 (lunes):
        // El horario general ya cerró a las 18:30, pero el operador tiene turno hasta las 22:00.
        Carbon::setTestNow(Carbon::parse('2026-10-19 20:00:00', 'America/La_Paz'));

        $action = app(DispatchOutOfHoursAutoReplyAction::class);
        $reply = $action->execute($conversation);

        // No debe enviar auto-respuesta porque Yerko sí está en su horario laboral diferenciado
        $this->assertNull($reply);

        // A las 23:00 (lunes): fuera de turno de Yerko
        Carbon::setTestNow(Carbon::parse('2026-10-19 23:00:00', 'America/La_Paz'));
        $replyNight = $action->execute($conversation);

        $this->assertNotNull($replyNight);
        $this->assertSame('Yerko está fuera de su turno especial.', $replyNight->body);

        Carbon::setTestNow();
    }
}

