<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Settings\Models\WorkspaceSetting;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase1FundationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_settings_can_be_retrieved_and_updated(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        // Show default settings
        $response = $this->actingAs($user)->getJson('/api/settings/workspace');
        $response->assertStatus(200)
            ->assertJsonPath('data.timezone', 'America/La_Paz');

        // Update settings
        $updateResponse = $this->actingAs($user)->putJson('/api/settings/workspace', [
            'timezone' => 'America/La_Paz',
            'hide_contact_data' => true,
            'enforce_2fa' => true,
            'sla_timeout_minutes' => 15,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.hide_contact_data', true)
            ->assertJsonPath('data.sla_timeout_minutes', 15);
    }

    public function test_tags_with_hex_color_can_be_created_and_listed(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $response = $this->actingAs($user)->postJson('/api/tags', [
            'name' => 'Cochabamba',
            'color_hex' => '#00a884',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Cochabamba')
            ->assertJsonPath('data.color_hex', '#00a884');

        $listResponse = $this->actingAs($user)->getJson('/api/tags');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_api_tokens_can_be_generated_and_listed(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $createResponse = $this->actingAs($user)->postJson('/api/tokens', [
            'name' => 'Webhook Token Test',
        ]);

        $createResponse->assertStatus(201)
            ->assertJsonStructure([
                'data' => ['id', 'name', 'plain_text_token', 'created_at'],
                'message',
            ]);

        $listResponse = $this->actingAs($user)->getJson('/api/tokens');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
