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
}
