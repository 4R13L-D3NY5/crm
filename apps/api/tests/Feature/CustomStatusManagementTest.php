<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomStatusManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'UNITEPC Status Test Org',
            'slug' => 'unitepc-status-test',
        ]);

        $this->user = User::create([
            'name' => 'Admin Status Test',
            'email' => 'admin-status@unitepc.test',
            'password' => bcrypt('secret123'),
            'current_organization_id' => $this->organization->id,
        ]);

        $this->user->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);
    }

    public function test_can_create_statuses_and_define_transition_dependencies(): void
    {
        $this->actingAs($this->user);

        // 1. Crear estado inicial: No Contactado
        $s1Response = $this->postJson('/api/custom-statuses', [
            'name' => 'No Contactado',
            'color' => '#3b82f6',
            'icon' => 'sym_r_mark_chat_unread',
            'stage_type' => 'initial',
            'is_default' => true,
        ]);
        $s1Response->assertCreated();
        $s1Id = $s1Response->json('data.id');

        // 2. Crear estado: Contactado (depende de No Contactado)
        $s2Response = $this->postJson('/api/custom-statuses', [
            'name' => 'Contactado',
            'color' => '#06b6d4',
            'stage_type' => 'in_progress',
            'allowed_previous_status_ids' => [$s1Id],
        ]);
        $s2Response->assertCreated();
        $s2Id = $s2Response->json('data.id');

        // 3. Crear estado: Inscrito (depende de Contactado)
        $s3Response = $this->postJson('/api/custom-statuses', [
            'name' => 'Inscrito',
            'color' => '#10b981',
            'stage_type' => 'won',
            'allowed_previous_status_ids' => [$s2Id],
        ]);
        $s3Response->assertCreated();
        $s3Id = $s3Response->json('data.id');

        // 4. Listar estados y verificar dependencias
        $listResponse = $this->getJson('/api/custom-statuses');
        $listResponse->assertOk();
        $this->assertCount(3, $listResponse->json('data'));

        $inscrito = collect($listResponse->json('data'))->firstWhere('id', $s3Id);
        $this->assertEquals([$s2Id], $inscrito['allowed_previous_status_ids']);
    }

    public function test_validates_transition_rules_when_updating_conversation_status(): void
    {
        $this->actingAs($this->user);

        // Estado A: No Contactado
        $noContactado = CustomStatus::create([
            'organization_id' => $this->organization->id,
            'name' => 'No Contactado',
            'slug' => 'no-contactado',
            'stage_type' => 'initial',
            'is_default' => true,
        ]);

        // Estado B: Contactado (Requiere No Contactado)
        $contactado = CustomStatus::create([
            'organization_id' => $this->organization->id,
            'name' => 'Contactado',
            'slug' => 'contactado',
            'stage_type' => 'in_progress',
        ]);
        $contactado->allowedPreviousStatuses()->sync([$noContactado->id]);

        // Estado C: Inscrito (Requiere Contactado)
        $inscrito = CustomStatus::create([
            'organization_id' => $this->organization->id,
            'name' => 'Inscrito',
            'slug' => 'inscrito',
            'stage_type' => 'won',
        ]);
        $inscrito->allowedPreviousStatuses()->sync([$contactado->id]);

        $conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'subject' => 'Postulante Medicina',
            'custom_status_id' => $noContactado->id,
        ]);

        // 1. Intento INVÁLIDO: Saltar directamente de No Contactado a Inscrito (debe fallar 422)
        $invalidResponse = $this->putJson("/api/conversations/{$conversation->id}/custom-status", [
            'custom_status_id' => $inscrito->id,
        ]);
        $invalidResponse->assertStatus(422);
        $this->assertStringContainsString('Transición no permitida', $invalidResponse->json('message'));

        // 2. Transición VÁLIDA: Pasar de No Contactado a Contactado
        $validResponse1 = $this->putJson("/api/conversations/{$conversation->id}/custom-status", [
            'custom_status_id' => $contactado->id,
            'note' => 'Primer mensaje respondido por el postulante',
        ]);
        $validResponse1->assertOk();
        $this->assertEquals($contactado->id, $conversation->fresh()->custom_status_id);

        // 3. Transición VÁLIDA: Ahora sí, de Contactado a Inscrito
        $validResponse2 = $this->putJson("/api/conversations/{$conversation->id}/custom-status", [
            'custom_status_id' => $inscrito->id,
            'note' => 'Matrícula pagada con comprobante',
        ]);
        $validResponse2->assertOk();
        $this->assertEquals($inscrito->id, $conversation->fresh()->custom_status_id);

        // Verificar que se crearon las notas internas en el timeline
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'is_internal' => true,
            'direction' => 'internal',
        ]);
    }

    public function test_can_seed_simplified_academic_statuses(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/custom-statuses/seed-academic');
        $response->assertOk();

        $this->assertDatabaseHas('custom_statuses', [
            'organization_id' => $this->organization->id,
            'name' => 'No Contactado',
            'is_default' => true,
        ]);

        $this->assertDatabaseHas('custom_statuses', [
            'organization_id' => $this->organization->id,
            'name' => 'Contactado',
        ]);

        $this->assertDatabaseHas('custom_statuses', [
            'organization_id' => $this->organization->id,
            'name' => 'Interesado / En Seguimiento',
        ]);

        $this->assertDatabaseHas('custom_statuses', [
            'organization_id' => $this->organization->id,
            'name' => 'Inscrito',
        ]);

        $this->assertDatabaseHas('custom_statuses', [
            'organization_id' => $this->organization->id,
            'name' => 'No Interesado / Descartado',
        ]);
    }
}
