<?php

namespace Tests\Feature\QuickMessages;

use App\Models\User;
use App\Modules\QuickMessages\Models\QuickMessage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickMessageCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_quick_messages_scoped_to_organization(): void
    {
        [$user, $organization] = $this->createMembership();

        // General message in user's organization
        QuickMessage::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => null,
            'shortcut' => 'bienvenida',
            'message' => '¡Hola! Bienvenido a XpertiFlow.',
            'is_general' => true,
        ]);

        // Personal message belonging to user
        QuickMessage::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => $user->getKey(),
            'shortcut' => 'mifirma',
            'message' => 'Atentamente, ' . $user->name,
            'is_general' => false,
        ]);

        // Another organization's message (must not appear)
        $otherOrg = Organization::factory()->create();
        QuickMessage::query()->create([
            'organization_id' => $otherOrg->getKey(),
            'user_id' => null,
            'shortcut' => 'otro_org',
            'message' => 'Mensaje privado de otra empresa',
            'is_general' => true,
        ]);

        $response = $this->actingAs($user)->getJson('/api/quick-messages');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['shortcut' => 'bienvenida'])
            ->assertJsonFragment(['shortcut' => 'mifirma'])
            ->assertJsonMissing(['shortcut' => 'otro_org']);
    }

    public function test_user_can_create_quick_message(): void
    {
        [$user, $organization] = $this->createMembership();

        $response = $this->actingAs($user)->postJson('/api/quick-messages', [
            'shortcut' => '/precios',
            'message' => 'Nuestros planes comienzan en Bs 150/mes.',
            'is_general' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.shortcut', 'precios')
            ->assertJsonPath('data.message', 'Nuestros planes comienzan en Bs 150/mes.')
            ->assertJsonPath('data.is_general', true);

        $this->assertDatabaseHas('quick_messages', [
            'organization_id' => $organization->getKey(),
            'shortcut' => 'precios',
            'is_general' => true,
        ]);
    }

    public function test_user_can_update_quick_message(): void
    {
        [$user, $organization] = $this->createMembership();

        $quickMessage = QuickMessage::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => null,
            'shortcut' => 'promo',
            'message' => 'Descuento del 10%',
            'is_general' => true,
        ]);

        $response = $this->actingAs($user)->putJson("/api/quick-messages/{$quickMessage->getKey()}", [
            'shortcut' => '/promo20',
            'message' => 'Descuento especial del 20%',
            'is_general' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.shortcut', 'promo20')
            ->assertJsonPath('data.message', 'Descuento especial del 20%');

        $this->assertDatabaseHas('quick_messages', [
            'id' => $quickMessage->getKey(),
            'shortcut' => 'promo20',
        ]);
    }

    public function test_user_can_delete_quick_message(): void
    {
        [$user, $organization] = $this->createMembership();

        $quickMessage = QuickMessage::query()->create([
            'organization_id' => $organization->getKey(),
            'user_id' => null,
            'shortcut' => 'despedida',
            'message' => '¡Hasta pronto!',
            'is_general' => true,
        ]);

        $response = $this->actingAs($user)->deleteJson("/api/quick-messages/{$quickMessage->getKey()}");

        $response->assertOk();

        $this->assertDatabaseMissing('quick_messages', [
            'id' => $quickMessage->getKey(),
        ]);
    }

    public function test_create_requires_shortcut_and_message(): void
    {
        [$user] = $this->createMembership();

        $response = $this->actingAs($user)->postJson('/api/quick-messages', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['shortcut', 'message']);
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
