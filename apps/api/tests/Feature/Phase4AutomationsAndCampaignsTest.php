<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Automations\Models\Campaign;
use App\Modules\Automations\Models\ScheduledMessage;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase4AutomationsAndCampaignsTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_message_can_be_created_and_listed(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);

        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $response = $this->actingAs($user)->postJson('/api/scheduled-messages', [
            'recipient_phone' => '+59177889901',
            'contact_name' => 'Carlos Mendoza',
            'body' => 'Recordatorio de inscripción para el semestre 2-2026',
            'scheduled_at' => Carbon::now()->addDays(2)->toDateTimeString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.recipient_phone', '+59177889901')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('scheduled_messages', [
            'organization_id' => $organization->id,
            'recipient_phone' => '+59177889901',
            'status' => 'pending',
        ]);

        $listResponse = $this->actingAs($user)->getJson('/api/scheduled-messages');
        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_mass_campaign_can_be_created_and_started(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);

        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        // Crear 3 contactos para la campaña
        Contact::create(['organization_id' => $organization->id, 'first_name' => 'Juan', 'phone' => '+59170000001', 'status' => 'active']);
        Contact::create(['organization_id' => $organization->id, 'first_name' => 'Maria', 'phone' => '+59170000002', 'status' => 'active']);
        Contact::create(['organization_id' => $organization->id, 'first_name' => 'Pedro', 'phone' => '+59170000003', 'status' => 'active']);

        $response = $this->actingAs($user)->postJson('/api/campaigns', [
            'name' => 'Campaña Beca Patriota 2026',
            'message_template' => 'Hola {{name}}, postúlate hoy a la Beca Patriota con 25% de descuento.',
            'delay_seconds' => 15,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.total_contacts', 3)
            ->assertJsonPath('data.status', 'draft');

        $campaignId = $response->json('data.id');

        // Iniciar disparo de campaña
        $startResponse = $this->actingAs($user)->postJson("/api/campaigns/{$campaignId}/start");
        $startResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'status' => 'processing',
        ]);

        // Pausar campaña
        $pauseResponse = $this->actingAs($user)->postJson("/api/campaigns/{$campaignId}/pause");
        $pauseResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'paused');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'status' => 'paused',
        ]);

        // Reanudar campaña
        $resumeResponse = $this->actingAs($user)->postJson("/api/campaigns/{$campaignId}/resume");
        $resumeResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'processing');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignId,
            'status' => 'processing',
        ]);
    }
}
