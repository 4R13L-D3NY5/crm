<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Parameters\Models\Category;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'UNITEPC Test Org',
            'slug' => 'unitepc-test',
        ]);

        $this->user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@unitepc.test',
            'password' => bcrypt('secret123'),
            'current_organization_id' => $this->organization->id,
        ]);

        $this->user->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);
    }

    public function test_can_create_root_category_and_subcategory(): void
    {
        $this->actingAs($this->user);

        // 1. Crear categoría raíz
        $response = $this->postJson('/api/categories', [
            'name' => 'Oferta Académica',
            'color' => '#10b981',
            'icon' => 'sym_r_school',
            'is_selectable' => false,
        ]);

        $response->assertCreated();
        $rootId = $response->json('data.id');
        $this->assertDatabaseHas('categories', [
            'id' => $rootId,
            'name' => 'Oferta Académica',
            'parent_id' => null,
            'organization_id' => $this->organization->id,
        ]);

        // 2. Crear subcategoría hija (Facultad)
        $subResponse = $this->postJson('/api/categories', [
            'name' => 'Facultad de Ingenierías',
            'parent_id' => $rootId,
            'color' => '#3b82f6',
            'icon' => 'sym_r_engineering',
            'is_selectable' => false,
        ]);

        $subResponse->assertCreated();
        $facultadId = $subResponse->json('data.id');

        // 3. Crear sub-subcategoría hoja (Carrera)
        $carreraResponse = $this->postJson('/api/categories', [
            'name' => 'Ingeniería de Sistemas',
            'parent_id' => $facultadId,
            'color' => '#6366f1',
            'icon' => 'sym_r_terminal',
            'is_selectable' => true,
        ]);

        $carreraResponse->assertCreated();
        $carreraId = $carreraResponse->json('data.id');

        // 4. Listar categorías y verificar árbol
        $listResponse = $this->getJson('/api/categories');
        $listResponse->assertOk();
        $this->assertCount(3, $listResponse->json('data'));
        $tree = $listResponse->json('tree');
        $this->assertCount(1, $tree); // Solo 1 raíz
        $this->assertEquals('Oferta Académica', $tree[0]['name']);
        $this->assertCount(1, $tree[0]['children']); // 1 facultad
        $this->assertEquals('Facultad de Ingenierías', $tree[0]['children'][0]['name']);
        $this->assertCount(1, $tree[0]['children'][0]['children']); // 1 carrera
        $this->assertEquals('Ingeniería de Sistemas', $tree[0]['children'][0]['children'][0]['name']);
    }

    public function test_can_assign_and_detach_category_to_conversation(): void
    {
        $this->actingAs($this->user);

        $category = Category::create([
            'organization_id' => $this->organization->id,
            'name' => 'Ingeniería de Sistemas',
            'slug' => 'ingenieria-de-sistemas',
            'color' => '#10b981',
            'is_selectable' => true,
        ]);

        $conversation = Conversation::create([
            'organization_id' => $this->organization->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'subject' => 'Consulta Postulante UNITEPC',
        ]);

        // Asignar categoría a la conversación
        $assignResponse = $this->postJson("/api/conversations/{$conversation->id}/categories", [
            'category_id' => $category->id,
        ]);

        $assignResponse->assertOk();
        $this->assertDatabaseHas('conversation_categories', [
            'conversation_id' => $conversation->id,
            'category_id' => $category->id,
            'organization_id' => $this->organization->id,
        ]);

        // Consultar categorías asignadas
        $getResponse = $this->getJson("/api/conversations/{$conversation->id}/categories");
        $getResponse->assertOk();
        $this->assertCount(1, $getResponse->json('data'));
        $this->assertEquals('Ingeniería de Sistemas', $getResponse->json('data.0.name'));

        // Desvincular categoría
        $detachResponse = $this->deleteJson("/api/conversations/{$conversation->id}/categories/{$category->id}");
        $detachResponse->assertOk();
        $this->assertDatabaseMissing('conversation_categories', [
            'conversation_id' => $conversation->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_can_seed_academic_template(): void
    {
        $this->actingAs($this->user);

        $response = $this->postJson('/api/categories/seed-template');
        $response->assertOk();

        $this->assertDatabaseHas('categories', [
            'organization_id' => $this->organization->id,
            'name' => 'Oferta Académica',
        ]);
        $this->assertDatabaseHas('categories', [
            'organization_id' => $this->organization->id,
            'name' => 'Ingeniería de Sistemas',
        ]);
        $this->assertDatabaseHas('categories', [
            'organization_id' => $this->organization->id,
            'name' => 'Sede La Paz',
        ]);
    }

    public function test_validates_unique_color_per_organization(): void
    {
        $this->actingAs($this->user);

        // Crear primera categoría con color #10b981
        $this->postJson('/api/categories', [
            'name' => 'Facultad de Medicina',
            'code' => 'MED',
            'color' => '#10b981',
        ])->assertCreated();

        // Intentar crear otra categoría con el mismo color
        $duplicateResponse = $this->postJson('/api/categories', [
            'name' => 'Facultad de Derecho',
            'code' => 'DER',
            'color' => '#10b981',
        ]);

        $duplicateResponse->assertStatus(422);
        $duplicateResponse->assertJsonValidationErrors(['color']);
    }

    public function test_can_reuse_same_category_under_multiple_parents(): void
    {
        $this->actingAs($this->user);

        // 1. Crear UNITEPC
        $unitepc = $this->postJson('/api/categories', [
            'name' => 'UNITEPC',
            'code' => 'UNITEPC',
            'color' => '#10b981',
        ])->json('data');

        // 2. Crear Facultad de Ingenierías (hija de UNITEPC)
        $facultad = $this->postJson('/api/categories', [
            'name' => 'Facultad de Ingenierías',
            'code' => 'FAC-ING',
            'parent_id' => $unitepc['id'],
            'color' => '#06b6d4',
        ])->json('data');

        // 3. Crear Sede La Paz (hija de UNITEPC)
        $sedeLpz = $this->postJson('/api/categories', [
            'name' => 'Sede La Paz',
            'code' => 'SEDE-LPZ',
            'parent_id' => $unitepc['id'],
            'color' => '#f59e0b',
        ])->json('data');

        // 4. Crear Ingeniería de Sistemas asignando múltiples padres
        $sistemas = $this->postJson('/api/categories', [
            'name' => 'Ingeniería de Sistemas',
            'code' => 'ING-SIS',
            'parent_ids' => [$facultad['id'], $sedeLpz['id']],
            'color' => '#3b82f6',
        ])->json('data');

        $this->assertDatabaseHas('category_parents', [
            'category_id' => $sistemas['id'],
            'parent_id' => $facultad['id'],
        ]);
        $this->assertDatabaseHas('category_parents', [
            'category_id' => $sistemas['id'],
            'parent_id' => $sedeLpz['id'],
        ]);

        // 5. Verificar que el árbol incluye a Ingeniería de Sistemas en ambas ramas
        $treeResponse = $this->getJson('/api/categories');
        $treeResponse->assertOk();
        $tree = $treeResponse->json('tree');

        $rootChildren = $tree[0]['children']; // Facultad y Sede La Paz
        $this->assertCount(2, $rootChildren);

        $facultadNode = collect($rootChildren)->firstWhere('name', 'Facultad de Ingenierías');
        $sedeNode = collect($rootChildren)->firstWhere('name', 'Sede La Paz');

        $this->assertEquals('Ingeniería de Sistemas', $facultadNode['children'][0]['name']);
        $this->assertTrue($facultadNode['children'][0]['is_shared']);

        $this->assertEquals('Ingeniería de Sistemas', $sedeNode['children'][0]['name']);
        $this->assertTrue($sedeNode['children'][0]['is_shared']);

        // Mismo ID de categoría compartido
        $this->assertEquals($sistemas['id'], $facultadNode['children'][0]['id']);
        $this->assertEquals($sistemas['id'], $sedeNode['children'][0]['id']);
    }
}
