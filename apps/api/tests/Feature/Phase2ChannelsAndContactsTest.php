<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2ChannelsAndContactsTest extends TestCase
{
    use RefreshDatabase;

    public function test_users_can_be_assigned_to_whatsapp_connection(): void
    {
        $user = User::factory()->create();
        $agent = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);

        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $agent->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);
        $user->update(['current_organization_id' => $organization->id]);

        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'WhatsApp Oficial Ventas',
            'phone_number_id' => '59177889900',
            'business_account_id' => '100200300400',
            'verify_token' => 'token-secret-123',
            'session_type' => 'qr_baileys',
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->putJson("/api/whatsapp/accounts/{$account->id}/users", [
            'users' => [
                [
                    'user_id' => $agent->id,
                    'can_view' => true,
                    'can_reply' => true,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Operadores asignados a la conexión exitosamente.');

        $this->assertDatabaseHas('whatsapp_account_users', [
            'whatsapp_account_id' => $account->id,
            'user_id' => $agent->id,
            'can_view' => true,
        ]);
    }

    public function test_contacts_can_be_imported_in_bulk_with_tags(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $response = $this->actingAs($user)->postJson('/api/contacts/import', [
            'contacts' => [
                [
                    'name' => 'Carlos Mendoza',
                    'phone' => '+591 77889901',
                    'email' => 'carlos.mendoza@gmail.com',
                    'tags' => ['Cochabamba', 'Admisiones 2026'],
                ],
                [
                    'name' => 'Lucia Rios',
                    'phone' => '+591 71234567',
                    'email' => 'lucia.rios@hotmail.com',
                    'tags' => ['Santa Cruz'],
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created_count', 2)
            ->assertJsonPath('data.total_processed', 2);

        $this->assertDatabaseHas('contacts', [
            'organization_id' => $organization->id,
            'phone' => '+59177889901',
            'first_name' => 'Carlos',
        ]);

        $this->assertDatabaseHas('tags', [
            'organization_id' => $organization->id,
            'name' => 'Cochabamba',
        ]);
    }
}
