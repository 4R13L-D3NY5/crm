<?php

namespace App\Modules\Contacts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $tags = Tag::where('organization_id', $organization->id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $query->where('name', 'ilike', "%{$request->input('search')}%");
            })
            ->latest()
            ->get();

        return response()->json(['data' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $tag = Tag::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']) . '-' . Str::random(4),
            'color_hex' => $validated['color_hex'] ?? '#00a884',
        ]);

        return response()->json([
            'data' => $tag,
            'message' => 'Etiqueta creada exitosamente.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $tag = Tag::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'color_hex' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']);
        }

        $tag->update($validated);

        return response()->json([
            'data' => $tag,
            'message' => 'Etiqueta actualizada exitosamente.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $tag = Tag::where('organization_id', $organization->id)->findOrFail($id);

        $tag->delete();

        return response()->json([
            'message' => 'Etiqueta eliminada exitosamente.',
        ]);
    }
}
