<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthorizationPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_profile_exposes_current_role_and_permissions(): void
    {
        [$user] = $this->createMembership('agent');

        $response = $this->actingAs($user)->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.current_role', 'agent')
            ->assertJsonPath('data.permissions.0', 'dashboard.view');
    }

    public function test_viewer_can_list_contacts_but_cannot_create_or_delete_them(): void
    {
        [$viewer, $organization] = $this->createMembership('viewer');

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
        ]);

        $this->actingAs($viewer)
            ->getJson('/api/contacts')
            ->assertOk();

        $this->actingAs($viewer)
            ->postJson('/api/contacts', [
                'first_name' => 'Nuevo',
                'last_name' => 'Viewer',
                'email' => 'viewer@example.com',
                'phone' => '70000000',
                'status' => 'lead',
                'notes' => 'No deberia poder crear.',
                'tags' => [],
            ])
            ->assertForbidden();

        $this->actingAs($viewer)
            ->deleteJson("/api/contacts/{$contact->getKey()}")
            ->assertForbidden();
    }

    public function test_agent_cannot_access_audit_logs_without_audit_permission(): void
    {
        [$user] = $this->createMembership('agent');

        $this->actingAs($user)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    private function createMembership(string $role): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->getKey(),
        ]);

        $organization->users()->attach($user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => $role,
        ]);

        return [$user, $organization];
    }
}
