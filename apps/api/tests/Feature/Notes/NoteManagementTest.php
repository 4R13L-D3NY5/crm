<?php

namespace Tests\Feature\Notes;

use App\Models\User;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Notes\Models\Note;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class NoteManagementTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;
    private User $adminUser;
    private User $agentUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'UNITEPC CRM Keep Test',
            'slug' => 'unitepc-keep-test',
        ]);

        // Usuario Administrador
        $this->adminUser = User::create([
            'name' => 'Admin Keeper',
            'email' => 'admin-keep@crm.test',
            'password' => bcrypt('password123'),
            'current_organization_id' => $this->organization->id,
        ]);
        $this->adminUser->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);

        // Usuario Asesor / Agente
        $this->agentUser = User::create([
            'name' => 'Agent Keeper',
            'email' => 'agent-keep@crm.test',
            'password' => bcrypt('password123'),
            'current_organization_id' => $this->organization->id,
        ]);
        $this->agentUser->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'agent',
        ]);
    }

    public function test_admin_can_create_note_with_categories_statuses_and_tags(): void
    {
        $this->actingAs($this->adminUser);

        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Medicina General',
            'color' => '#10b981',
        ]);

        $customStatus = CustomStatus::create([
            'organization_id' => $this->organization->id,
            'name' => 'Interesado',
            'stage_type' => 'in_progress',
        ]);

        $tag = Tag::create([
            'organization_id' => $this->organization->id,
            'name' => 'Beca 50%',
            'slug' => 'beca-50',
            'color_hex' => '#00a884',
        ]);

        $response = $this->postJson('/api/notes', [
            'title' => 'Requisitos de Admisión 2026',
            'content' => 'Para postular se requiere fotocopia de título de bachiller y carnet de identidad.',
            'color' => '#1e3a5f',
            'shortcut' => '#medicina2026',
            'visibility' => 'categories',
            'category_ids' => [$category->id],
            'custom_status_ids' => [$customStatus->id],
            'tag_ids' => [$tag->id],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Requisitos de Admisión 2026')
            ->assertJsonPath('data.shortcut', 'medicina2026')
            ->assertJsonPath('data.visibility', 'categories');

        $this->assertDatabaseHas('notes', [
            'title' => 'Requisitos de Admisión 2026',
            'shortcut' => 'medicina2026',
            'organization_id' => $this->organization->id,
        ]);

        $this->assertDatabaseHas('note_categories', [
            'category_id' => $category->id,
        ]);

        $this->assertDatabaseHas('note_custom_statuses', [
            'custom_status_id' => $customStatus->id,
        ]);

        $this->assertDatabaseHas('note_tags', [
            'tag_id' => $tag->id,
        ]);
    }

    public function test_agent_cannot_create_update_or_delete_note(): void
    {
        $this->actingAs($this->agentUser);

        // Intento de creación por un agente -> 403 Forbidden
        $response = $this->postJson('/api/notes', [
            'title' => 'Nota de prueba por agente',
            'content' => 'Contenido de la nota',
        ]);
        $response->assertStatus(403);

        // Nota existente creada por admin
        $note = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Nota de Admin',
            'content' => 'Contenido oficial',
            'visibility' => 'all',
        ]);

        // Intento de actualización por agente -> 403 Forbidden
        $updateResponse = $this->putJson("/api/notes/{$note->id}", [
            'title' => 'Intento de edición',
            'content' => 'Modificado',
        ]);
        $updateResponse->assertStatus(403);

        // Intento de eliminación por agente -> 403 Forbidden
        $deleteResponse = $this->deleteJson("/api/notes/{$note->id}");
        $deleteResponse->assertStatus(403);
    }

    public function test_note_visibility_rules_for_agents(): void
    {
        // 1. Categoría A y Categoría B
        $catA = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Odontología',
        ]);

        $catB = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Derecho',
        ]);

        // Asignar al agente únicamente la Categoría A
        $this->agentUser->categories()->attach($catA->id, [
            'organization_id' => $this->organization->id,
        ]);

        // 2. Crear 4 notas:
        // Nota 1: 'all' -> visible para todos
        $noteAll = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Nota General',
            'content' => 'Para todo el equipo',
            'visibility' => 'all',
        ]);

        // Nota 2: 'private' creada por admin -> oculta para el agente
        $notePrivate = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Nota Privada Admin',
            'content' => 'Solo administradores',
            'visibility' => 'private',
        ]);

        // Nota 3: 'categories' vinculada a Cat A (Odontología) -> visible para el agente
        $noteCatA = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Nota Odonto',
            'content' => 'Contenido odontológico',
            'visibility' => 'categories',
        ]);
        $noteCatA->categories()->attach($catA->id);

        // Nota 4: 'categories' vinculada a Cat B (Derecho) -> oculta para el agente
        $noteCatB = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Nota Derecho',
            'content' => 'Contenido jurídico',
            'visibility' => 'categories',
        ]);
        $noteCatB->categories()->attach($catB->id);

        // 3. Consultar como Asesor
        $this->actingAs($this->agentUser);
        $agentList = $this->getJson('/api/notes');
        $agentList->assertStatus(200);

        $visibleIds = collect($agentList->json('data'))->pluck('id')->all();

        $this->assertContains($noteAll->id, $visibleIds, 'La nota general debe ser visible para el agente');
        $this->assertContains($noteCatA->id, $visibleIds, 'La nota con categoría compartida debe ser visible para el agente');
        $this->assertNotContains($notePrivate->id, $visibleIds, 'La nota privada no debe ser visible para el agente');
        $this->assertNotContains($noteCatB->id, $visibleIds, 'La nota de otra categoría no asignada debe estar oculta para el agente');

        // 4. Consultar como Administrador (debe ver todas)
        $this->actingAs($this->adminUser);
        $adminList = $this->getJson('/api/notes');
        $adminList->assertStatus(200);

        $adminVisibleIds = collect($adminList->json('data'))->pluck('id')->all();
        $this->assertContains($noteAll->id, $adminVisibleIds);
        $this->assertContains($notePrivate->id, $adminVisibleIds);
        $this->assertContains($noteCatA->id, $adminVisibleIds);
        $this->assertContains($noteCatB->id, $adminVisibleIds);
    }

    public function test_admin_can_toggle_pin_and_archive_notes(): void
    {
        $this->actingAs($this->adminUser);

        $note = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Nota Pin Test',
            'content' => 'Texto a fijar',
            'is_pinned' => false,
            'is_archived' => false,
        ]);

        // Alternar Pin
        $pinRes = $this->patchJson("/api/notes/{$note->id}/pin");
        $pinRes->assertStatus(200)
            ->assertJsonPath('data.is_pinned', true);
        $this->assertTrue($note->fresh()->is_pinned);

        // Alternar Archivo
        $archiveRes = $this->patchJson("/api/notes/{$note->id}/archive");
        $archiveRes->assertStatus(200)
            ->assertJsonPath('data.is_archived', true);
        $this->assertTrue($note->fresh()->is_archived);
    }

    public function test_entity_analyzer_detects_categories_statuses_and_tags(): void
    {
        $this->actingAs($this->agentUser);

        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Fisioterapia',
        ]);

        $customStatus = CustomStatus::create([
            'organization_id' => $this->organization->id,
            'name' => 'Postulante',
            'stage_type' => 'in_progress',
        ]);

        $tag = Tag::create([
            'organization_id' => $this->organization->id,
            'name' => 'Convenio Empresa',
            'slug' => 'convenio-empresa',
        ]);

        $response = $this->postJson('/api/notes/analyze', [
            'content' => 'El estudiante desea inscribirse en Fisioterapia como nuevo Postulante bajo el Convenio Empresa.',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_detected', 3);

        $detectedCatIds = collect($response->json('data.detected_categories'))->pluck('id')->all();
        $detectedStatusIds = collect($response->json('data.detected_custom_statuses'))->pluck('id')->all();
        $detectedTagIds = collect($response->json('data.detected_tags'))->pluck('id')->all();

        $this->assertContains($category->id, $detectedCatIds);
        $this->assertContains($customStatus->id, $detectedStatusIds);
        $this->assertContains($tag->id, $detectedTagIds);
    }

    public function test_ai_refine_permissions_and_validation(): void
    {
        // Agente intenta usar AI refine -> 403
        $this->actingAs($this->agentUser);
        $agentRes = $this->postJson('/api/notes/ai/refine', [
            'content' => 'Texto a corregir',
            'action' => 'spellcheck',
        ]);
        $agentRes->assertStatus(403);

        // Admin con acción inválida -> 422
        $this->actingAs($this->adminUser);
        $invalidRes = $this->postJson('/api/notes/ai/refine', [
            'content' => 'Texto de prueba',
            'action' => 'invalid_action',
        ]);
        $invalidRes->assertStatus(422)
            ->assertJsonValidationErrors(['action']);

        // Admin con acción válida pero sin API key configurada en tenant -> 422 con código AI_CONFIG_MISSING
        $validRes = $this->postJson('/api/notes/ai/refine', [
            'content' => 'Texto para corregir ortografia',
            'action' => 'spellcheck',
        ]);
        $validRes->assertStatus(422)
            ->assertJsonPath('code', 'AI_CONFIG_MISSING');
    }
}
