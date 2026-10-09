<?php

namespace App\Modules\Parameters\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Parameters\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $categories = Category::query()
            ->where('organization_id', $organization->id)
            ->with(['parents', 'children'])
            ->withCount('conversations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Construir árbol para el frontend soportando ramas cruzadas (Grafo/Multi-padre)
        $tree = $this->buildTree($categories);

        $usedColors = $categories->pluck('color')->filter()->map(fn ($c) => strtolower(trim($c)))->unique()->values()->all();

        return response()->json([
            'data' => $categories->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'parent_id' => $c->parent_id,
                'parent_ids' => $c->parents->pluck('id')->all(),
                'parents' => $c->parents->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'code' => $p->code,
                    'color' => $p->color,
                ])->all(),
                'is_shared' => $c->parents->count() > 1,
                'name' => $c->name,
                'slug' => $c->slug,
                'color' => $c->color,
                'icon' => $c->icon,
                'is_selectable' => $c->is_selectable,
                'sort_order' => $c->sort_order,
                'conversations_count' => $c->conversations_count,
                'full_path' => $c->full_path,
            ]),
            'tree' => $tree,
            'used_colors' => $usedColors,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'color' => [
                'required',
                'string',
                'max:30',
                Rule::unique('categories', 'color')->where('organization_id', $organization->id),
            ],
            'icon' => ['nullable', 'string', 'max:60'],
            'is_selectable' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'string', 'exists:categories,id'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['exists:categories,id'],
        ], [
            'color.unique' => 'El color seleccionado ya está registrado en otra categoría. Cada categoría debe tener un color único.',
        ]);

        // Unificar parent_ids
        $parentIds = [];
        if (!empty($validated['parent_ids'])) {
            $parentIds = $validated['parent_ids'];
        } elseif (!empty($validated['parent_id'])) {
            $parentIds = [$validated['parent_id']];
        }
        $parentIds = array_unique(array_filter($parentIds));

        // Validar que pertenezcan a la organización
        if (!empty($parentIds)) {
            $count = Category::where('organization_id', $organization->id)
                ->whereIn('id', $parentIds)
                ->count();
            if ($count !== count($parentIds)) {
                return response()->json(['message' => 'Una o más categorías padre no son válidas.'], 422);
            }
        }

        $category = Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $parentIds[0] ?? null,
            'name' => $validated['name'],
            'code' => !empty($validated['code']) ? strtoupper(trim($validated['code'])) : null,
            'slug' => Str::slug($validated['name']),
            'color' => strtolower(trim($validated['color'])),
            'icon' => $validated['icon'] ?? null,
            'is_selectable' => $validated['is_selectable'] ?? true,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        // Asignar padres en la tabla pivote category_parents
        if (!empty($parentIds)) {
            foreach ($parentIds as $pId) {
                $category->parents()->attach($pId, [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organization->id,
                    'sort_order' => $category->sort_order,
                ]);
            }
        }

        $category->load('parents');

        return response()->json([
            'data' => $category,
            'message' => 'Categoría creada exitosamente.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $category = Category::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'color' => [
                'sometimes',
                'string',
                'max:30',
                Rule::unique('categories', 'color')
                    ->where('organization_id', $organization->id)
                    ->ignore($category->id),
            ],
            'icon' => ['nullable', 'string', 'max:60'],
            'is_selectable' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'parent_id' => ['nullable', 'string', 'exists:categories,id'],
            'parent_ids' => ['nullable', 'array'],
            'parent_ids.*' => ['exists:categories,id'],
        ], [
            'color.unique' => 'El color seleccionado ya está registrado en otra categoría. Cada categoría debe tener un color único.',
        ]);

        // Manejar actualización de padres si se envían
        if (array_key_exists('parent_ids', $validated) || array_key_exists('parent_id', $validated)) {
            $parentIds = [];
            if (isset($validated['parent_ids'])) {
                $parentIds = $validated['parent_ids'];
            } elseif (isset($validated['parent_id'])) {
                $parentIds = $validated['parent_id'] ? [$validated['parent_id']] : [];
            }
            $parentIds = array_unique(array_filter($parentIds));

            // Evitar que una categoría sea su propio padre
            if (in_array($category->id, $parentIds, true)) {
                return response()->json([
                    'message' => 'Una categoría no puede tenerse a sí misma como categoría padre.',
                ], 422);
            }

            $syncData = [];
            foreach ($parentIds as $pId) {
                $syncData[$pId] = [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organization->id,
                    'sort_order' => $validated['sort_order'] ?? $category->sort_order,
                ];
            }
            $category->parents()->sync($syncData);
            $category->parent_id = $parentIds[0] ?? null;
        }

        $category->update([
            'name' => $validated['name'] ?? $category->name,
            'code' => array_key_exists('code', $validated) ? (!empty($validated['code']) ? strtoupper(trim($validated['code'])) : null) : $category->code,
            'color' => isset($validated['color']) ? strtolower(trim($validated['color'])) : $category->color,
            'icon' => array_key_exists('icon', $validated) ? $validated['icon'] : $category->icon,
            'is_selectable' => $validated['is_selectable'] ?? $category->is_selectable,
            'sort_order' => $validated['sort_order'] ?? $category->sort_order,
        ]);

        $category->load('parents');

        return response()->json([
            'data' => $category,
            'message' => 'Categoría actualizada exitosamente.',
        ]);
    }

    /**
     * Vincula una categoría existente a una nueva rama padre (reutilización)
     */
    public function linkParent(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $category = Category::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'parent_id' => ['required', 'string', 'exists:categories,id'],
        ]);

        $parentId = $validated['parent_id'];

        if ($parentId === $category->id) {
            return response()->json(['message' => 'No puedes vincular una categoría consigo misma.'], 422);
        }

        $parent = Category::where('organization_id', $organization->id)->findOrFail($parentId);

        // Verificar si ya está vinculada
        if (!$category->parents()->where('parent_id', $parentId)->exists()) {
            $category->parents()->attach($parentId, [
                'id' => (string) Str::ulid(),
                'organization_id' => $organization->id,
                'sort_order' => $category->sort_order,
            ]);

            if (empty($category->parent_id)) {
                $category->parent_id = $parentId;
                $category->save();
            }
        }

        return response()->json([
            'message' => "Categoría '{$category->name}' vinculada exitosamente bajo '{$parent->name}'.",
        ]);
    }

    /**
     * Desvincula una categoría de una rama padre específica sin eliminarla del sistema
     */
    public function unlinkParent(Request $request, string $id, string $parentId): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $category = Category::where('organization_id', $organization->id)->findOrFail($id);

        $category->parents()->detach($parentId);

        // Si su parent_id principal era este, asignar el siguiente padre disponible o null
        if ($category->parent_id === $parentId) {
            $nextParent = $category->parents()->first();
            $category->parent_id = $nextParent?->id;
            $category->save();
        }

        return response()->json([
            'message' => 'Categoría desvinculada de la rama exitosamente.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $category = Category::where('organization_id', $organization->id)->findOrFail($id);

        $category->delete();

        return response()->json([
            'message' => 'Categoría eliminada exitosamente.',
        ]);
    }

    /**
     * Siembra rápida de plantilla de ejemplo universitaria con soporte de ramas cruzadas
     */
    public function seedTemplate(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        // 1. Rama Oferta Académica
        $academica = Category::create([
            'organization_id' => $organization->id,
            'name' => 'Oferta Académica',
            'code' => 'OFE-ACAD',
            'slug' => 'oferta-academica',
            'color' => '#10b981',
            'icon' => 'sym_r_school',
            'is_selectable' => false,
            'sort_order' => 1,
        ]);

        $ingenierias = Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $academica->id,
            'name' => 'Facultad de Ingenierías',
            'code' => 'FAC-ING',
            'slug' => 'facultad-ingenierias',
            'color' => '#06b6d4',
            'icon' => 'sym_r_engineering',
            'is_selectable' => false,
            'sort_order' => 1,
        ]);
        $ingenierias->parents()->attach($academica->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
        ]);

        $sistemas = Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $ingenierias->id,
            'name' => 'Ingeniería de Sistemas',
            'code' => 'ING-SIS',
            'slug' => 'ingenieria-de-sistemas',
            'color' => '#3b82f6',
            'icon' => 'sym_r_terminal',
            'is_selectable' => true,
            'sort_order' => 1,
        ]);
        $sistemas->parents()->attach($ingenierias->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
        ]);

        $electronica = Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $ingenierias->id,
            'name' => 'Ingeniería Electrónica',
            'code' => 'ING-ELEC',
            'slug' => 'ingenieria-electronica',
            'color' => '#6366f1',
            'icon' => 'sym_r_memory',
            'is_selectable' => true,
            'sort_order' => 2,
        ]);
        $electronica->parents()->attach($ingenierias->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
        ]);

        $salud = Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $academica->id,
            'name' => 'Ciencias de la Salud',
            'code' => 'FAC-SALUD',
            'slug' => 'ciencias-de-la-salud',
            'color' => '#ec4899',
            'icon' => 'sym_r_medical_services',
            'is_selectable' => false,
            'sort_order' => 2,
        ]);
        $salud->parents()->attach($academica->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
        ]);

        $medicina = Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $salud->id,
            'name' => 'Medicina',
            'code' => 'MED',
            'slug' => 'medicina',
            'color' => '#f43f5e',
            'icon' => 'sym_r_cardiology',
            'is_selectable' => true,
            'sort_order' => 1,
        ]);
        $medicina->parents()->attach($salud->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
        ]);

        // 2. Rama Sedes
        $sedes = Category::create([
            'organization_id' => $organization->id,
            'name' => 'Sedes Institucionales',
            'code' => 'SEDES',
            'slug' => 'sedes-institucionales',
            'color' => '#f59e0b',
            'icon' => 'sym_r_location_city',
            'is_selectable' => false,
            'sort_order' => 2,
        ]);

        $sedeLaPaz = Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $sedes->id,
            'name' => 'Sede La Paz',
            'code' => 'SEDE-LPZ',
            'slug' => 'sede-la-paz',
            'color' => '#eab308',
            'icon' => 'sym_r_apartment',
            'is_selectable' => true,
            'sort_order' => 1,
        ]);
        $sedeLaPaz->parents()->attach($sedes->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
        ]);

        $sedeCbba = Category::create([
            'organization_id' => $organization->id,
            'parent_id' => $sedes->id,
            'name' => 'Sede Cochabamba',
            'code' => 'SEDE-CBB',
            'slug' => 'sede-cochabamba',
            'color' => '#84cc16',
            'icon' => 'sym_r_apartment',
            'is_selectable' => true,
            'sort_order' => 2,
        ]);
        $sedeCbba->parents()->attach($sedes->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
        ]);

        // 3. DEMOSTRACIÓN DE REUTILIZACIÓN MULTI-PADRE (Ingeniería de Sistemas existe una vez pero cuelga de Fac. Ingenierías y Sede La Paz)
        $sistemas->parents()->attach($sedeLaPaz->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
            'sort_order' => 1,
        ]);

        $sistemas->parents()->attach($sedeCbba->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
            'sort_order' => 1,
        ]);

        $medicina->parents()->attach($sedeCbba->id, [
            'id' => (string) Str::ulid(),
            'organization_id' => $organization->id,
            'sort_order' => 2,
        ]);

        return response()->json([
            'message' => 'Plantilla académica universitaria con carreras reutilizadas cargada exitosamente.',
        ]);
    }

    /**
     * Construye la representación de árbol jerárquico soportando nodos compartidos en múltiples ramas
     */
    private function buildTree($categories): array
    {
        // 1. Identificar nodos raíz: aquellos sin ningún padre
        $rootCategories = $categories->filter(function ($c) {
            return $c->parents->isEmpty();
        });

        $tree = [];
        foreach ($rootCategories as $root) {
            $tree[] = $this->buildNode($root, $categories, [$root->id]);
        }

        return $tree;
    }

    private function buildNode(Category $node, $allCategories, array $visitedPath): array
    {
        // Buscar hijos: todas aquellas categorías que tienen a $node->id en sus parents
        $childNodes = $allCategories->filter(function ($c) use ($node) {
            return $c->parents->contains('id', $node->id);
        });

        $children = [];
        foreach ($childNodes as $child) {
            // Protección contra ciclos (A -> B -> A)
            if (in_array($child->id, $visitedPath, true)) {
                continue;
            }

            $newPath = array_merge($visitedPath, [$child->id]);
            $children[] = $this->buildNode($child, $allCategories, $newPath);
        }

        return [
            'id' => $node->id,
            'code' => $node->code,
            'parent_id' => $node->parent_id,
            'parent_ids' => $node->parents->pluck('id')->all(),
            'parents' => $node->parents->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'color' => $p->color,
            ])->all(),
            'is_shared' => $node->parents->count() > 1,
            'parents_count' => $node->parents->count(),
            'name' => $node->name,
            'slug' => $node->slug,
            'color' => $node->color,
            'icon' => $node->icon,
            'is_selectable' => (bool) $node->is_selectable,
            'sort_order' => $node->sort_order,
            'conversations_count' => $node->conversations_count ?? 0,
            'children' => $children,
        ];
    }
}
