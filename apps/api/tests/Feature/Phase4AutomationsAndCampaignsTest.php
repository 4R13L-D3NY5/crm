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

    public function test_mass_campaign_can_filter_by_categories_and_preview_recipients(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);

        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        // Crear categorías: Facultad Salud (padre) y Medicina (hijo)
        $parentCat = \App\Modules\Parameters\Models\Category::create([
            'organization_id' => $organization->id,
            'name' => 'Salud',
            'code' => 'SALUD',
            'color' => '#10b981',
        ]);

        $childCat = \App\Modules\Parameters\Models\Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $parentCat->id,
            'name' => 'Medicina',
            'code' => 'MED',
            'color' => '#06b6d4',
        ]);

        \Illuminate\Support\Facades\DB::table('category_parents')->insert([
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
            'category_id' => $childCat->id,
            'parent_id' => $parentCat->id,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Contacto 1 con categoría Medicina directa
        $contact1 = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Estudiante Medicina', 'phone' => '+59171111111', 'status' => 'active']);
        $contact1->categories()->attach($childCat->id, ['id' => (string) Str::ulid(), 'organization_id' => $organization->id]);

        // Contacto 2 con conversación clasificada en Salud
        $contact2 = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Consulta Salud', 'phone' => '+59172222222', 'status' => 'active']);
        $conv2 = \App\Modules\Conversations\Models\Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact2->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);
        $conv2->categories()->attach($parentCat->id, ['id' => (string) Str::ulid(), 'organization_id' => $organization->id]);

        // Contacto 3 sin categorías
        Contact::create(['organization_id' => $organization->id, 'first_name' => 'Otro Contacto', 'phone' => '+59173333333', 'status' => 'active']);

        // 1. Preview de destinatarios filtrados por la categoría padre (debe incluir el hijo Medicina y la conversación Salud)
        $previewRes = $this->actingAs($user)->postJson('/api/campaigns/preview-recipients', [
            'target_mode' => 'categories',
            'category_ids' => [$parentCat->id],
        ]);

        $previewRes->assertStatus(200)
            ->assertJsonPath('count', 2)
            ->assertJsonCount(2, 'samples');

        // 2. Crear campaña por categorías
        $campaignRes = $this->actingAs($user)->postJson('/api/campaigns', [
            'name' => 'Campaña Convocatoria Salud 2026',
            'message_template' => '{Hola|Estimado/a} {{name}}, postúlate al área de salud de UNITEPC.',
            'target_mode' => 'categories',
            'category_ids' => [$parentCat->id],
            'delay_seconds' => 10,
        ]);

        $campaignRes->assertStatus(201)
            ->assertJsonPath('data.total_contacts', 2)
            ->assertJsonPath('data.target_type', 'categories');

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaignRes->json('data.id'),
            'target_type' => 'categories',
            'total_contacts' => 2,
        ]);

        $this->assertDatabaseCount('campaign_recipients', 2);
    }
}
