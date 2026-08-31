<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrentUserTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_fetch_profile_and_organizations(): void
    {
        [$user, $organization] = $this->createMembership();

        $response = $this->actingAs($user)->getJson('/api/me');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $user->getKey())
            ->assertJsonPath('data.current_organization.id', $organization->getKey())
            ->assertJsonCount(1, 'data.organizations');
    }

    public function test_authenticated_user_can_switch_current_organization(): void
    {
        [$user] = $this->createMembership();
        $secondOrganization = Organization::factory()->create();

        $secondOrganization->users()->attach($user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'member',
        ]);

        $response = $this->actingAs($user)->putJson('/api/organizations/current', [
            'organization_id' => $secondOrganization->getKey(),
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $secondOrganization->getKey());

        $this->assertDatabaseHas('users', [
            'id' => $user->getKey(),
            'current_organization_id' => $secondOrganization->getKey(),
        ]);
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
