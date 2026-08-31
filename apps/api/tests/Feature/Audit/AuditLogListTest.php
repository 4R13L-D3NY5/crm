<?php

namespace Tests\Feature\Audit;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use App\Shared\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_audit_logs_for_current_organization(): void
    {
        [$user, $organization] = $this->createMembership();
        $otherOrganization = Organization::factory()->create();

        AuditLog::factory()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'event' => 'auth.login',
        ]);

        AuditLog::factory()->create([
            'organization_id' => $otherOrganization->getKey(),
            'event' => 'auth.login',
        ]);

        $response = $this->actingAs($user)->getJson('/api/audit-logs');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'auth.login');
    }

    public function test_user_can_filter_audit_logs_by_event_and_user(): void
    {
        [$user, $organization] = $this->createMembership();
        $secondUser = User::factory()->create([
            'current_organization_id' => $organization->getKey(),
        ]);

        $organization->users()->attach($secondUser->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'agent',
        ]);

        AuditLog::factory()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'event' => 'auth.login',
        ]);

        AuditLog::factory()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $secondUser->getKey(),
            'event' => 'whatsapp.webhook_received',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/audit-logs?event=whatsapp.webhook_received&user_id='.$secondUser->getKey()
        );

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event', 'whatsapp.webhook_received')
            ->assertJsonPath('data.0.user.id', $secondUser->getKey());
    }

    private function createMembership(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->getKey(),
        ]);

        $organization->users()->attach($user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'owner',
        ]);

        return [$user, $organization];
    }
}
