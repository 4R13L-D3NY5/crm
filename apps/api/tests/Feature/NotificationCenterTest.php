<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

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
}
