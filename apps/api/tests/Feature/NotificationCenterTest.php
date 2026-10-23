<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Parameters\Models\TimeAlertRule;
use App\Modules\Social\Models\SocialComment;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Carbon\Carbon;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_notifications_and_unread_count(): void
    {
        $org = Organization::create(['name' => 'Org Notif', 'slug' => 'org-notif-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        AppNotification::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'chat',
            'title' => 'Chat Nuevo',
            'message' => 'Tienes un nuevo mensaje de WhatsApp.',
        ]);

        AppNotification::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Aviso Sistema',
            'message' => 'Mantenimiento completado.',
            'read_at' => now(),
        ]);

        $res = $this->actingAs($user)->getJson('/api/notifications');

        $res->assertStatus(200);
        $res->assertJsonPath('unread_count', 1);
        $res->assertJsonCount(2, 'notifications');
    }

    public function test_user_can_filter_notifications_by_type(): void
    {
        $org = Organization::create(['name' => 'Org Filter', 'slug' => 'org-filter-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        AppNotification::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'chat',
            'title' => 'Chat',
            'message' => 'Msg',
        ]);

        AppNotification::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'billing',
            'title' => 'Billing',
            'message' => 'Invoice',
        ]);

        $res = $this->actingAs($user)->getJson('/api/notifications?type=billing');

        $res->assertStatus(200);
        $res->assertJsonCount(1, 'notifications');
        $res->assertJsonPath('notifications.0.type', 'billing');
    }

    public function test_user_can_mark_single_notification_as_read(): void
    {
        $org = Organization::create(['name' => 'Org Mark', 'slug' => 'org-mark-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $notif = AppNotification::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'chat',
            'title' => 'Alerta',
            'message' => 'Contenido',
        ]);

        $res = $this->actingAs($user)->putJson("/api/notifications/{$notif->id}/read");

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);

        $notif->refresh();
        $this->assertNotNull($notif->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        $org = Organization::create(['name' => 'Org All', 'slug' => 'org-all-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        AppNotification::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'chat',
            'title' => '1',
            'message' => '1',
        ]);

        AppNotification::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => '2',
            'message' => '2',
        ]);

        $res = $this->actingAs($user)->putJson('/api/notifications/read-all');

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('updated_count', 2);

        $this->assertEquals(0, AppNotification::query()->unread()->count());
    }

    public function test_user_can_delete_notification(): void
    {
        $org = Organization::create(['name' => 'Org Del', 'slug' => 'org-del-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $notif = AppNotification::create([
            'organization_id' => $org->id,
            'user_id' => $user->id,
            'type' => 'system',
            'title' => 'Delete me',
            'message' => 'Content',
        ]);

        $res = $this->actingAs($user)->deleteJson("/api/notifications/{$notif->id}");

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $this->assertDatabaseMissing('app_notifications', ['id' => $notif->id]);
    }

    public function test_operator_inactivity_triggers_waiting_advisor_notification(): void
    {
        $org = Organization::create(['name' => 'Org SLA', 'slug' => 'org-sla-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);

        // Regla SLA: 20 min asesor, 120 min cliente
        TimeAlertRule::create([
            'organization_id' => $org->id,
            'name' => 'Regla SLA General',
            'user_timeout_minutes' => 20,
            'client_timeout_minutes' => 120,
            'notify_user_inactivity' => true,
            'notify_client_inactivity' => true,
            'apply_to_all_statuses' => true,
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Carlos',
            'phone' => '+59178899001',
        ]);

        $conv = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => Carbon::now()->subMinutes(25),
        ]);

        // Mensaje entrante del cliente hace 25 min (supera 20 min SLA)
        $msg = Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'is_internal' => false,
            'body' => 'Hola, sigo esperando información.',
        ]);
        $msg->created_at = Carbon::now()->subMinutes(25);
        $msg->save();

        $res = $this->actingAs($user)->getJson('/api/notifications');

        $res->assertStatus(200);
        $res->assertJsonPath('counts.waiting_advisor', 1);
        $res->assertJsonPath('notifications.0.type', 'waiting_advisor');
        $res->assertJsonPath('notifications.0.title', 'Sin respuesta del Asesor: Carlos');
    }

    public function test_client_inactivity_triggers_waiting_client_notification(): void
    {
        $org = Organization::create(['name' => 'Org Client SLA', 'slug' => 'org-client-sla-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);

        // Regla SLA: 20 min asesor, 120 min cliente
        TimeAlertRule::create([
            'organization_id' => $org->id,
            'name' => 'Regla SLA General',
            'user_timeout_minutes' => 20,
            'client_timeout_minutes' => 120,
            'notify_user_inactivity' => true,
            'notify_client_inactivity' => true,
            'apply_to_all_statuses' => true,
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Lucia',
            'phone' => '+59178899002',
        ]);

        $conv = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => Carbon::now()->subMinutes(130),
        ]);

        // Mensaje saliente del asesor hace 130 min (supera 120 min de inactividad de cliente)
        $msg = Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'is_internal' => false,
            'body' => '¿Pudiste revisar la cotización que te enviamos?',
        ]);
        $msg->created_at = Carbon::now()->subMinutes(130);
        $msg->save();

        $res = $this->actingAs($user)->getJson('/api/notifications');

        $res->assertStatus(200);
        $res->assertJsonPath('counts.waiting_client', 1);
        $res->assertJsonPath('notifications.0.type', 'waiting_client');
        $res->assertJsonPath('notifications.0.title', 'Sin respuesta del Cliente: Lucia');
    }

    public function test_custom_parameterized_time_alert_rule_is_applied(): void
    {
        $org = Organization::create(['name' => 'Org Param', 'slug' => 'org-param-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);

        // Regla personalizada muy estricta: 5 min asesor, 30 min cliente
        TimeAlertRule::create([
            'organization_id' => $org->id,
            'name' => 'Regla Express Urgente',
            'user_timeout_minutes' => 5,
            'client_timeout_minutes' => 30,
            'notify_user_inactivity' => true,
            'notify_client_inactivity' => true,
            'apply_to_all_statuses' => true,
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Daniel',
            'phone' => '+59178899003',
        ]);

        $conv = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => Carbon::now()->subMinutes(7),
        ]);

        // Mensaje entrante hace 7 min (supera el parámetro de 5 min)
        $msg = Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'is_internal' => false,
            'body' => 'Consulta urgente.',
        ]);
        $msg->created_at = Carbon::now()->subMinutes(7);
        $msg->save();

        $res = $this->actingAs($user)->getJson('/api/notifications');

        $res->assertStatus(200);
        $res->assertJsonPath('advisor_timeout_minutes', 5);
        $res->assertJsonPath('client_timeout_minutes', 30);
        $res->assertJsonPath('counts.waiting_advisor', 1);
    }

    public function test_internal_note_does_not_mask_waiting_advisor_sla(): void
    {
        $org = Organization::create(['name' => 'Org Internal SLA', 'slug' => 'org-internal-sla-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);

        TimeAlertRule::create([
            'organization_id' => $org->id,
            'name' => 'Regla SLA General',
            'user_timeout_minutes' => 20,
            'client_timeout_minutes' => 120,
            'notify_user_inactivity' => true,
            'notify_client_inactivity' => true,
            'apply_to_all_statuses' => true,
            'is_active' => true,
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Elena',
            'phone' => '+59178899004',
        ]);

        $conv = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'unread_count' => 0,
            'last_message_at' => Carbon::now()->subMinutes(25),
        ]);

        // 1. Cliente envía mensaje hace 25 min (inbound)
        $inbound = Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'is_internal' => false,
            'body' => 'Necesito ayuda con mi trámite.',
        ]);
        $inbound->created_at = Carbon::now()->subMinutes(25);
        $inbound->save();

        // 2. Operador deja nota interna hace 5 min (is_internal = true)
        // La nota interna NO debe interpretarse como respuesta al cliente
        $note = Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'is_internal' => true,
            'body' => 'Nota interna: verificar expediente antes de llamar.',
        ]);
        $note->created_at = Carbon::now()->subMinutes(5);
        $note->save();

        $res = $this->actingAs($user)->getJson('/api/notifications');

        $res->assertStatus(200);
        // Debe alertar que el asesor aún no ha respondido al cliente (SLA vencido hace 25m)
        $res->assertJsonPath('counts.waiting_advisor', 1);
        $res->assertJsonPath('notifications.0.type', 'waiting_advisor');
        $res->assertJsonPath('notifications.0.title', 'Sin respuesta del Asesor: Elena');
    }

    public function test_notifications_include_multichannel_metadata_and_post_provenance(): void
    {
        $org = Organization::create(['name' => 'Org Multi', 'slug' => 'org-multi-' . uniqid()]);
        $user = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($user->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $account = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'name' => 'Escuela De Postgrado Unitepc',
            'session_type' => 'facebook',
            'phone_number_id' => '10293847',
            'verify_token' => 'verify_token_sample',
            'is_active' => true,
        ]);

        $account->authorizedUsers()->attach($user->id, [
            'id' => (string) Str::ulid(),
            'can_view' => true,
            'can_reply' => true,
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Dominique',
            'last_name' => 'Cruz',
        ]);

        $conv = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'channel' => 'facebook',
            'whatsapp_account_id' => $account->id,
            'status' => 'open',
            'unread_count' => 2,
            'last_message_at' => Carbon::now()->subMinutes(5),
        ]);

        SocialComment::create([
            'organization_id' => $org->id,
            'conversation_id' => $conv->id,
            'contact_id' => $contact->id,
            'platform' => 'facebook',
            'post_id' => '1029384756',
            'comment_id' => 'comment_abc',
            'author_name' => 'Dominique Cruz',
            'comment_text' => 'Me interesa la maestria en derecho',
        ]);

        $res = $this->actingAs($user)->getJson('/api/notifications');

        $res->assertStatus(200);
        $res->assertJsonPath('unread_count', 1);

        $notification = collect($res->json('notifications'))->firstWhere('type', 'unread');
        $this->assertNotNull($notification, 'Debe existir una notificación de tipo unread');
        $this->assertEquals('facebook', $notification['data']['channel'] ?? null);
        $this->assertEquals('Escuela De Postgrado Unitepc', $notification['data']['channel_name'] ?? null);
        $this->assertTrue($notification['data']['is_from_post'] ?? false);
        $this->assertEquals('1029384756', $notification['data']['post_id'] ?? null);
    }

    public function test_operator_does_not_receive_notifications_from_unauthorized_channels(): void
    {
        $org = Organization::create(['name' => 'Org Strict Channels', 'slug' => 'org-strict-' . uniqid()]);
        $agent = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($agent->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $channelCbba = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'name' => 'WhatsApp Cochabamba',
            'phone_number_id' => '59170000001',
            'verify_token' => 'token_59170000001',
            'session_type' => 'baileys_qr',
            'is_active' => true,
        ]);
        $channelLaPaz = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'name' => 'WhatsApp La Paz',
            'phone_number_id' => '59170000002',
            'verify_token' => 'token_59170000002',
            'session_type' => 'baileys_qr',
            'is_active' => true,
        ]);

        // Autorizar al agente ÚNICAMENTE en Cochabamba
        $channelCbba->authorizedUsers()->attach($agent->id, [
            'id' => (string) Str::ulid(),
            'can_view' => true,
            'can_reply' => true,
        ]);

        $contact1 = Contact::create(['organization_id' => $org->id, 'first_name' => 'Cliente Cbba', 'phone' => '+59171111111']);
        $contact2 = Contact::create(['organization_id' => $org->id, 'first_name' => 'Cliente La Paz', 'phone' => '+59172222222']);

        // Conversación sin asignar en Cochabamba con no leídos
        $convCbba = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact1->id,
            'whatsapp_account_id' => $channelCbba->id,
            'status' => 'open',
            'unread_count' => 1,
            'last_message_at' => Carbon::now(),
        ]);

        // Conversación sin asignar en La Paz con no leídos
        $convLaPaz = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact2->id,
            'whatsapp_account_id' => $channelLaPaz->id,
            'status' => 'open',
            'unread_count' => 1,
            'last_message_at' => Carbon::now(),
        ]);

        $res = $this->actingAs($agent)->getJson('/api/notifications');

        $res->assertStatus(200);
        $res->assertJsonPath('unread_count', 1);
        $notifs = collect($res->json('notifications'));
        $this->assertCount(1, $notifs);
        $this->assertEquals("conv_unread_{$convCbba->id}", $notifs->first()['id']);
    }

    public function test_operator_does_not_receive_notifications_for_chats_assigned_to_other_operators(): void
    {
        $org = Organization::create(['name' => 'Org Assign Isolation', 'slug' => 'org-assign-' . uniqid()]);
        $agentA = User::factory()->create(['current_organization_id' => $org->id, 'name' => 'Carlos']);
        $agentB = User::factory()->create(['current_organization_id' => $org->id, 'name' => 'Juan']);
        $org->users()->attach($agentA->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);
        $org->users()->attach($agentB->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        TimeAlertRule::create([
            'organization_id' => $org->id,
            'name' => 'Regla SLA General',
            'user_timeout_minutes' => 15,
            'client_timeout_minutes' => 120,
            'notify_user_inactivity' => true,
            'notify_client_inactivity' => true,
            'apply_to_all_statuses' => true,
            'is_active' => true,
        ]);

        $channel = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'name' => 'Línea Central',
            'phone_number_id' => '59170000003',
            'verify_token' => 'token_59170000003',
            'session_type' => 'baileys_qr',
            'is_active' => true,
        ]);

        // Ambos agentes están autorizados en la línea central
        $channel->authorizedUsers()->attach($agentA->id, ['id' => (string) Str::ulid(), 'can_view' => true, 'can_reply' => true]);
        $channel->authorizedUsers()->attach($agentB->id, ['id' => (string) Str::ulid(), 'can_view' => true, 'can_reply' => true]);

        $contact = Contact::create(['organization_id' => $org->id, 'first_name' => 'Mario', 'phone' => '+59173333333']);

        // Chat asignado exclusivamente a Juan (agentB), con SLA vencido y no leído
        $conv = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $channel->id,
            'assigned_to_user_id' => $agentB->id,
            'status' => 'open',
            'unread_count' => 3,
            'last_message_at' => Carbon::now()->subMinutes(20),
        ]);

        $msg = Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'is_internal' => false,
            'body' => 'Necesito ayuda.',
        ]);
        $msg->created_at = Carbon::now()->subMinutes(20);
        $msg->save();

        // 1. Carlos (agentA) NO debe recibir alertas del chat de Juan
        $resA = $this->actingAs($agentA)->getJson('/api/notifications');
        $resA->assertStatus(200);
        $resA->assertJsonPath('unread_count', 0);
        $resA->assertJsonCount(0, 'notifications');

        // 2. Juan (agentB) SÍ debe recibir las alertas de su chat
        $resB = $this->actingAs($agentB)->getJson('/api/notifications');
        $resB->assertStatus(200);
        $this->assertGreaterThan(0, $resB->json('unread_count'));
        $bNotifs = collect($resB->json('notifications'));
        $this->assertTrue($bNotifs->contains(fn ($n) => $n['data']['conversation_id'] === $conv->id));
    }

    public function test_operator_with_no_assigned_channels_only_receives_their_own_assigned_chats(): void
    {
        $org = Organization::create(['name' => 'Org No Channels', 'slug' => 'org-no-chan-' . uniqid()]);
        $agent = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($agent->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $channel = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'name' => 'Línea Sin Asignar',
            'phone_number_id' => '59170000004',
            'verify_token' => 'token_59170000004',
            'session_type' => 'baileys_qr',
            'is_active' => true,
        ]);
        // No se asigna ningún usuario al canal

        $contact1 = Contact::create(['organization_id' => $org->id, 'first_name' => 'Contacto 1', 'phone' => '+59174444441']);
        $contact2 = Contact::create(['organization_id' => $org->id, 'first_name' => 'Contacto 2', 'phone' => '+59174444442']);

        // Chat sin asignar en el canal
        Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact1->id,
            'whatsapp_account_id' => $channel->id,
            'status' => 'open',
            'unread_count' => 2,
            'last_message_at' => Carbon::now(),
        ]);

        // Chat asignado directamente al agente
        $myConv = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact2->id,
            'whatsapp_account_id' => $channel->id,
            'assigned_to_user_id' => $agent->id,
            'status' => 'open',
            'unread_count' => 1,
            'last_message_at' => Carbon::now(),
        ]);

        $res = $this->actingAs($agent)->getJson('/api/notifications');
        $res->assertStatus(200);
        $res->assertJsonPath('unread_count', 1);
        $notifs = collect($res->json('notifications'));
        $this->assertCount(1, $notifs);
        $this->assertEquals("conv_unread_{$myConv->id}", $notifs->first()['id']);
    }

    public function test_admin_receives_notifications_across_all_channels_and_operators(): void
    {
        $org = Organization::create(['name' => 'Org Admin Global', 'slug' => 'org-admin-' . uniqid()]);
        $admin = User::factory()->create(['current_organization_id' => $org->id]);
        $agent = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($admin->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $org->users()->attach($agent->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $channel1 = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'name' => 'Línea 1',
            'phone_number_id' => '59170000005',
            'verify_token' => 'token_59170000005',
            'session_type' => 'baileys_qr',
            'is_active' => true,
        ]);
        $channel2 = WhatsAppAccount::create([
            'organization_id' => $org->id,
            'name' => 'Línea 2',
            'phone_number_id' => '59170000006',
            'verify_token' => 'token_59170000006',
            'session_type' => 'baileys_qr',
            'is_active' => true,
        ]);

        $c1 = Contact::create(['organization_id' => $org->id, 'first_name' => 'C1', 'phone' => '+59175555551']);
        $c2 = Contact::create(['organization_id' => $org->id, 'first_name' => 'C2', 'phone' => '+59175555552']);

        $conv1 = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $c1->id,
            'whatsapp_account_id' => $channel1->id,
            'assigned_to_user_id' => $agent->id,
            'status' => 'open',
            'unread_count' => 1,
            'last_message_at' => Carbon::now(),
        ]);

        $conv2 = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $c2->id,
            'whatsapp_account_id' => $channel2->id,
            'status' => 'open',
            'unread_count' => 1,
            'last_message_at' => Carbon::now(),
        ]);

        // El admin ve ambas alertas de ambos canales y de todos los operadores
        $res = $this->actingAs($admin)->getJson('/api/notifications');
        $res->assertStatus(200);
        $res->assertJsonPath('unread_count', 2);
    }

    public function test_operator_marking_all_read_does_not_clear_unread_count_of_other_operators(): void
    {
        $org = Organization::create(['name' => 'Org Mark Isolation', 'slug' => 'org-mark-iso-' . uniqid()]);
        $agentA = User::factory()->create(['current_organization_id' => $org->id]);
        $agentB = User::factory()->create(['current_organization_id' => $org->id]);
        $org->users()->attach($agentA->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);
        $org->users()->attach($agentB->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $c1 = Contact::create(['organization_id' => $org->id, 'first_name' => 'C1', 'phone' => '+59176666661']);
        $c2 = Contact::create(['organization_id' => $org->id, 'first_name' => 'C2', 'phone' => '+59176666662']);

        $convA = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $c1->id,
            'assigned_to_user_id' => $agentA->id,
            'status' => 'open',
            'unread_count' => 5,
        ]);

        $convB = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $c2->id,
            'assigned_to_user_id' => $agentB->id,
            'status' => 'open',
            'unread_count' => 3,
        ]);

        // AgentA marca todas como leídas
        $res = $this->actingAs($agentA)->putJson('/api/notifications/read-all');
        $res->assertStatus(200);

        $convA->refresh();
        $convB->refresh();

        $this->assertEquals(0, $convA->unread_count, 'Los no leídos de AgentA deben ser 0');
        $this->assertEquals(3, $convB->unread_count, 'Los no leídos de AgentB NO deben haberse borrado');
    }
}
