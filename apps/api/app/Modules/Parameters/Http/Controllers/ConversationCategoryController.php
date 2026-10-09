<?php

namespace App\Modules\Parameters\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Parameters\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ConversationCategoryController extends Controller
{
    public function index(Request $request, string $conversationId): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $conversation = Conversation::where('organization_id', $organization->id)->findOrFail($conversationId);

        $categories = $conversation->categories()
            ->with('parent')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'code' => $c->code,
                'parent_id' => $c->parent_id,
                'name' => $c->name,
                'color' => $c->color,
                'icon' => $c->icon,
                'full_path' => $c->full_path,
                'parent_name' => $c->parent?->name,
            ]);

        return response()->json([
            'data' => $categories,
        ]);
    }

    public function store(Request $request, string $conversationId): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $conversation = Conversation::where('organization_id', $organization->id)->findOrFail($conversationId);

        $validated = $request->validate([
            'category_id' => ['nullable', 'string', 'exists:categories,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
        ]);

        $idsToAttach = [];
        if (!empty($validated['category_id'])) {
            $idsToAttach[] = $validated['category_id'];
        }
        if (!empty($validated['category_ids'])) {
            $idsToAttach = array_unique(array_merge($idsToAttach, $validated['category_ids']));
        }

        if (empty($idsToAttach)) {
            return response()->json(['message' => 'Debe indicar al menos una categoría.'], 422);
        }

        // Verificar que pertenezcan a la organización
        $validCategories = Category::where('organization_id', $organization->id)
            ->whereIn('id', $idsToAttach)
            ->get();

        foreach ($validCategories as $category) {
            $conversation->categories()->syncWithoutDetaching([
                $category->id => [
                    'id' => (string) Str::ulid(),
                    'organization_id' => $organization->id,
                    'assigned_by_user_id' => $request->user()->id,
                ],
            ]);
        }

        $freshCategories = $conversation->categories()->with('parent')->get()->map(fn ($c) => [
            'id' => $c->id,
            'code' => $c->code,
            'name' => $c->name,
            'color' => $c->color,
            'icon' => $c->icon,
            'full_path' => $c->full_path,
            'parent_name' => $c->parent?->name,
        ]);

        return response()->json([
            'data' => $freshCategories,
            'message' => 'Categoría asignada a la conversación correctamente.',
        ]);
    }

    public function destroy(Request $request, string $conversationId, string $categoryId): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $conversation = Conversation::where('organization_id', $organization->id)->findOrFail($conversationId);

        $conversation->categories()->detach($categoryId);

        return response()->json([
            'message' => 'Categoría desvinculada de la conversación.',
        ]);
    }
}
