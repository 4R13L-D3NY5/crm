<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use App\Shared\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase11AuditLogsAndSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_audit_logs_can_be_listed_and_filtered(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'ticket.transferred',
            'metadata' => ['from_queue' => 'Admisiones', 'to_queue' => 'Financiera'],
            'ip_address' => '192.168.1.100',
        ]);

        $response = $this->actingAs($user)->getJson('/api/audit-logs');

        $response->assertStatus(200)
            ->assertJsonPath('data.0.event', 'ticket.transferred')
            ->assertJsonPath('data.0.ip_address', '192.168.1.100');
    }

    public function test_audit_logs_summary_returns_security_metrics(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'supervisor.ghost_message',
            'metadata' => ['ticket_id' => '01m123456'],
        ]);

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'ticket.transferred',
            'metadata' => ['ticket_id' => '01m123457'],
        ]);

        $response = $this->actingAs($user)->getJson('/api/audit-logs/summary');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_events', 2)
            ->assertJsonPath('data.supervisor_interventions', 1)
            ->assertJsonPath('data.ticket_transfers', 1);
    }

    public function test_audit_logs_export_returns_structured_data(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        AuditLog::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'event' => 'settings.updated',
            'metadata' => ['sla_unanswered_minutes' => 15],
            'ip_address' => '127.0.0.1',
        ]);

        $response = $this->actingAs($user)->getJson('/api/audit-logs/export');

        $response->assertStatus(200)
            ->assertJsonPath('data.count', 1)
            ->assertJsonPath('data.records.0.event', 'settings.updated');
    }
}
