<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Queues\Models\Queue;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase12UserManagementAndPresenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_operator_with_queues_and_role(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $admin->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $admin->update(['current_organization_id' => $organization->id]);

        $queue = Queue::create(['organization_id' => $organization->id, 'name' => 'Admisiones', 'color' => '#10b981', 'is_active' => true]);

        $response = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Carla Mendoza',
            'email' => 'carla.mendoza@unitepc.edu.bo',
            'password' => 'Secreto123*',
            'role' => 'agent',
            'queue_ids' => [$queue->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Carla Mendoza')
            ->assertJsonPath('data.role', 'agent')
            ->assertJsonPath('data.presence_status', 'offline');

        $this->assertDatabaseHas('users', ['email' => 'carla.mendoza@unitepc.edu.bo']);
    }

    public function test_user_presence_status_can_be_updated(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);
        $user->update(['current_organization_id' => $organization->id]);

        $response = $this->actingAs($user)->putJson("/api/users/{$user->id}/presence", [
            'presence_status' => 'online',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.presence_status', 'online');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'presence_status' => 'online',
        ]);
    }

    public function test_admin_can_update_operator_queues(): void
    {
        $admin = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $admin->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $admin->update(['current_organization_id' => $organization->id]);

        $agent = User::factory()->create(['current_organization_id' => $organization->id]);
        $agent->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $queue1 = Queue::create(['organization_id' => $organization->id, 'name' => 'Fila 1', 'color' => '#10b981', 'is_active' => true]);
        $queue2 = Queue::create(['organization_id' => $organization->id, 'name' => 'Fila 2', 'color' => '#06b6d4', 'is_active' => true]);

        $response = $this->actingAs($admin)->putJson("/api/users/{$agent->id}", [
            'name' => 'Agente Renombrado',
            'role' => 'supervisor',
            'queue_ids' => [$queue1->id, $queue2->id],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Agente Renombrado')
            ->assertJsonPath('data.role', 'supervisor');

        $this->assertDatabaseHas('queue_users', [
            'user_id' => $agent->id,
            'queue_id' => $queue1->id,
        ]);
    }
}
