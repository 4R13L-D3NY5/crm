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

    public function test_agent_can_create_and_manage_own_notes_but_cannot_modify_others_notes(): void
    {
        $this->actingAs($this->agentUser);

        // 1. Creación por un agente -> 201 Created con autor asignado
        $createResponse = $this->postJson('/api/notes', [
            'title' => 'Nota creada por Asesor',
            'content' => 'Respuestas rápidas para dudas de postulantes.',
            'visibility' => 'all',
        ]);
        $createResponse->assertStatus(201)
            ->assertJsonPath('data.title', 'Nota creada por Asesor')
            ->assertJsonPath('data.user_id', $this->agentUser->id)
            ->assertJsonPath('data.user.name', 'Agent Keeper');

        $agentNoteId = $createResponse->json('data.id');

        // 2. Agente actualiza su propia nota -> 200 OK
        $updateOwnResponse = $this->putJson("/api/notes/{$agentNoteId}", [
            'title' => 'Nota de Asesor Actualizada',
            'content' => 'Contenido modificado por el autor.',
        ]);
        $updateOwnResponse->assertStatus(200)
            ->assertJsonPath('data.title', 'Nota de Asesor Actualizada');

        // 3. Nota existente creada por el administrador
        $adminNote = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Nota de Admin',
            'content' => 'Contenido oficial de directivos',
            'visibility' => 'all',
        ]);

        // 4. Intento de actualización de nota de otro usuario por agente -> 403 Forbidden
        $updateResponse = $this->putJson("/api/notes/{$adminNote->id}", [
            'title' => 'Intento no autorizado',
            'content' => 'Modificado',
        ]);
        $updateResponse->assertStatus(403);

        // 5. Intento de eliminación de nota de otro usuario por agente -> 403 Forbidden
        $deleteResponse = $this->deleteJson("/api/notes/{$adminNote->id}");
        $deleteResponse->assertStatus(403);

        // 6. Agente elimina su propia nota -> 200 OK
        $deleteOwnResponse = $this->deleteJson("/api/notes/{$agentNoteId}");
        $deleteOwnResponse->assertStatus(200);
        $this->assertSoftDeleted('notes', ['id' => $agentNoteId]);
    }

    public function test_finding_similar_notes_endpoint(): void
    {
        $this->actingAs($this->adminUser);

        $note1 = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Requisitos de Admisión para Medicina',
            'content' => 'Para postular se requiere fotocopia legalizada del título de bachiller, certificado de nacimiento y carnet de identidad.',
            'visibility' => 'all',
        ]);

        $note2 = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->agentUser->id,
            'title' => 'Documentos de Matrícula e Inscripción',
            'content' => 'Presentar fotocopia del título de bachiller y carnet de identidad vigente en ventanilla de admisión.',
            'visibility' => 'all',
        ]);

        $note3 = Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Inventario de computadoras y red',
            'content' => 'Servidores y switches ubicados en el rack del piso 3 con cables ethernet cat6.',
            'visibility' => 'all',
        ]);

        // Buscar notas similares a la Nota 1
        $response = $this->getJson("/api/notes/{$note1->id}/similar");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'note' => ['id', 'title', 'content', 'user_id', 'user'],
                        'similarity_percent',
                        'similarity_reason',
                        'source',
                    ],
                ],
                'target_note',
                'total',
            ]);

        $similarNotes = $response->json('data');
        $this->assertNotEmpty($similarNotes);
        // La nota 2 debe tener mayor similitud que la nota 3
        $firstMatch = $similarNotes[0];
        $this->assertEquals($note2->id, $firstMatch['note']['id']);
        $this->assertGreaterThanOrEqual(25, $firstMatch['similarity_percent']);
        $this->assertNotEmpty($firstMatch['similarity_reason']);
        $this->assertEquals('Agent Keeper', $firstMatch['note']['user']['name']);

        // Probar también búsqueda sobre borrador de texto
        $draftResponse = $this->postJson('/api/notes/similar', [
            'title' => 'Requisitos para extranjeros',
            'content' => 'Trámite de postulación y presentación de título de bachiller con carnet legalizado.',
        ]);
        $draftResponse->assertStatus(200)
            ->assertJsonStructure(['data', 'total']);
        $this->assertNotEmpty($draftResponse->json('data'));
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

    public function test_owner_can_create_and_manage_notes(): void
    {
        $owner = User::create([
            'name' => 'Owner Keeper',
            'email' => 'owner-keep@crm.test',
            'password' => bcrypt('password123'),
            'current_organization_id' => $this->organization->id,
        ]);
        $owner->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'owner',
        ]);

        $this->actingAs($owner);

        // Crear nota como owner
        $createRes = $this->postJson('/api/notes', [
            'title' => 'Nota creada por Owner',
            'content' => 'Contenido de la nota creada por el owner de la organización.',
            'color' => '#1e293b',
            'visibility' => 'all',
        ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('data.title', 'Nota creada por Owner');

        $noteId = $createRes->json('data.id');

        // Toggle pin como owner
        $pinRes = $this->patchJson("/api/notes/{$noteId}/pin");
        $pinRes->assertStatus(200)
            ->assertJsonPath('data.is_pinned', true);

        // Toggle archive como owner
        $archiveRes = $this->patchJson("/api/notes/{$noteId}/archive");
        $archiveRes->assertStatus(200)
            ->assertJsonPath('data.is_archived', true);

        // Delete como owner
        $deleteRes = $this->deleteJson("/api/notes/{$noteId}");
        $deleteRes->assertStatus(200);

        $this->assertSoftDeleted('notes', ['id' => $noteId]);
    }

    public function test_user_can_preview_keep_zip_file(): void
    {
        $this->actingAs($this->adminUser);

        // Crear archivo ZIP temporal con nota JSON
        $zipPath = tempnam(sys_get_temp_dir(), 'keep_test_') . '.zip';
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZipArchive::CREATE);
        $keepJson = json_encode([
            'title' => 'Ingeniería de Sonido Cochabamba',
            'textContent' => 'Costos de la carrera en Cochabamba: 1200 Bs.',
            'color' => 'TEAL',
            'isPinned' => true,
        ]);
        $zip->addFromString('Takeout/Keep/nota1.json', $keepJson);
        $zip->close();

        $uploadedFile = new \Illuminate\Http\UploadedFile(
            $zipPath,
            'takeout.zip',
            'application/zip',
            null,
            true
        );

        $response = $this->post('/api/notes/preview-keep', [
            'file' => $uploadedFile,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.total_detected', 1)
            ->assertJsonPath('data.notes.0.title', 'Ingeniería de Sonido Cochabamba');

        @unlink($zipPath);
    }

    public function test_user_can_import_batch_notes(): void
    {
        $this->actingAs($this->adminUser);

        $response = $this->postJson('/api/notes/import-batch', [
            'notes' => [
                [
                    'title' => 'Nota Lote 1',
                    'content' => 'Contenido de prueba lote 1',
                    'shortcut' => 'lote1',
                    'color' => '#14b8a6',
                    'is_pinned' => true,
                ],
                [
                    'title' => 'Nota Lote 2',
                    'content' => 'Contenido de prueba lote 2',
                    'shortcut' => 'lote2',
                    'color' => '#3b82f6',
                    'is_pinned' => false,
                ],
            ],
            'index_ai' => true,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.imported_count', 2);

        $this->assertDatabaseHas('notes', [
            'title' => 'Nota Lote 1',
            'shortcut' => 'lote1',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_user_can_upload_document_note(): void
    {
        $this->actingAs($this->adminUser);

        $file = \Illuminate\Http\UploadedFile::fake()->createWithContent(
            'reglamento_becas.txt',
            "Reglamento Oficial de Becas UNITEPC\n1. Beca a la Excelencia\n2. Beca Socioeconómica"
        );

        $response = $this->post('/api/notes/upload-document', [
            'file' => $file,
            'title' => 'Reglamento Oficial de Becas',
            'shortcut' => 'becas_oficial',
            'index_ai' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Reglamento Oficial de Becas')
            ->assertJsonPath('data.shortcut', 'becas_oficial');

        $this->assertDatabaseHas('notes', [
            'title' => 'Reglamento Oficial de Becas',
            'shortcut' => 'becas_oficial',
            'organization_id' => $this->organization->id,
        ]);
    }

    public function test_permissive_search_matches_non_correlative_words_in_notes(): void
    {
        $this->actingAs($this->adminUser);

        Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Carrera de Medicina Humana',
            'content' => 'Para la sede de Cochabamba el costo de arancel mensual es 1200 Bs con beca.',
            'shortcut' => 'med_cbba',
            'color' => '#1e293b',
            'visibility' => 'all',
        ]);

        // 1. En búsqueda tradicional correlativa, palabras salteadas NO coinciden
        $resStandard = $this->getJson('/api/notes?search=medicina+cochabamba+arancel');
        $resStandard->assertStatus(200);
        $this->assertCount(0, $resStandard->json('data'));

        // 2. En búsqueda permisiva, palabras no correlativas SÍ coinciden
        $resPermissive = $this->getJson('/api/notes?search=medicina+cochabamba+arancel&permissive=true');
        $resPermissive->assertStatus(200);
        $this->assertCount(1, $resPermissive->json('data'));
        $this->assertSame('Carrera de Medicina Humana', $resPermissive->json('data.0.title'));
    }

    public function test_similarity_draft_supports_algorithmic_and_ai_modes(): void
    {
        $this->actingAs($this->adminUser);

        Note::create([
            'organization_id' => $this->organization->id,
            'user_id' => $this->adminUser->id,
            'title' => 'Requisitos de Admisión para Extranjeros',
            'content' => 'Documentación necesaria: pasaporte vigente, título de bachiller legalizado y visa de estudiante.',
            'shortcut' => 'admision_ext',
            'color' => '#14b8a6',
            'visibility' => 'all',
        ]);

        // Modo algorítmico explícito
        $algoRes = $this->postJson('/api/notes/similar', [
            'content' => 'Requisitos y documentos para estudiantes extranjeros pasaporte bachiller',
            'mode' => 'algorithmic',
        ]);

        $algoRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($algoRes->json('data')));

        // Modo IA
        $aiRes = $this->postJson('/api/notes/similar', [
            'content' => 'Requisitos y documentos para estudiantes extranjeros pasaporte bachiller',
            'mode' => 'ai',
        ]);

        $aiRes->assertStatus(200);
        $this->assertIsArray($aiRes->json('data'));
    }
}
