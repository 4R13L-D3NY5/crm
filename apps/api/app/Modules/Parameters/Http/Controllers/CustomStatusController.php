<?php

namespace App\Modules\Parameters\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Parameters\Models\CustomStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CustomStatusController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $statuses = CustomStatus::query()
            ->where('organization_id', $organization->id)
            ->with(['allowedPreviousStatuses'])
            ->withCount('conversations')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $statuses->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'slug' => $s->slug,
                'color' => $s->color,
                'icon' => $s->icon,
                'stage_type' => $s->stage_type,
                'is_default' => $s->is_default,
                'sort_order' => $s->sort_order,
                'is_active' => $s->is_active,
                'conversations_count' => $s->conversations_count,
                'allowed_previous_status_ids' => $s->allowedPreviousStatuses->pluck('id')->toArray(),
                'allowed_previous_statuses' => $s->allowedPreviousStatuses->map(fn ($prev) => [
                    'id' => $prev->id,
                    'name' => $prev->name,
                    'color' => $prev->color,
                ]),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:60'],
            'stage_type' => ['nullable', 'string', 'in:initial,in_progress,won,lost'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'allowed_previous_status_ids' => ['nullable', 'array'],
            'allowed_previous_status_ids.*' => ['exists:custom_statuses,id'],
        ]);

        if (!empty($validated['is_default'])) {
            CustomStatus::where('organization_id', $organization->id)->update(['is_default' => false]);
        }

        $status = CustomStatus::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']),
            'color' => $validated['color'] ?? '#10b981',
            'icon' => $validated['icon'] ?? 'sym_r_flag',
            'stage_type' => $validated['stage_type'] ?? 'in_progress',
            'is_default' => $validated['is_default'] ?? false,
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
        ]);

        if (array_key_exists('allowed_previous_status_ids', $validated)) {
            $status->allowedPreviousStatuses()->sync($validated['allowed_previous_status_ids'] ?? []);
        }

        return response()->json([
            'data' => $status->load('allowedPreviousStatuses'),
            'message' => 'Estado personalizado creado exitosamente.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $status = CustomStatus::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:60'],
            'stage_type' => ['sometimes', 'string', 'in:initial,in_progress,won,lost'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'allowed_previous_status_ids' => ['nullable', 'array'],
            'allowed_previous_status_ids.*' => ['exists:custom_statuses,id'],
        ]);

        if (!empty($validated['is_default'])) {
            CustomStatus::where('organization_id', $organization->id)
                ->where('id', '!=', $status->id)
                ->update(['is_default' => false]);
        }

        $status->update([
            'name' => $validated['name'] ?? $status->name,
            'color' => $validated['color'] ?? $status->color,
            'icon' => array_key_exists('icon', $validated) ? $validated['icon'] : $status->icon,
            'stage_type' => $validated['stage_type'] ?? $status->stage_type,
            'is_default' => array_key_exists('is_default', $validated) ? $validated['is_default'] : $status->is_default,
            'sort_order' => $validated['sort_order'] ?? $status->sort_order,
            'is_active' => array_key_exists('is_active', $validated) ? $validated['is_active'] : $status->is_active,
        ]);

        if (array_key_exists('allowed_previous_status_ids', $validated)) {
            // Evitar auto-referencia
            $filtered = array_diff($validated['allowed_previous_status_ids'] ?? [], [$status->id]);
            $status->allowedPreviousStatuses()->sync($filtered);
        }

        return response()->json([
            'data' => $status->load('allowedPreviousStatuses'),
            'message' => 'Estado personalizado actualizado exitosamente.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $status = CustomStatus::where('organization_id', $organization->id)
            ->withCount('conversations')
            ->findOrFail($id);

        if ($status->conversations_count > 0) {
            return response()->json([
                'message' => "No se puede eliminar el estado porque tiene {$status->conversations_count} conversaciones asignadas.",
            ], 422);
        }

        $status->delete();

        return response()->json([
            'message' => 'Estado eliminado exitosamente.',
        ]);
    }

    /**
     * Siembra rápida del flujo de estados simplificado para universidades/leads:
     * No Contactado -> Contactado -> Interesado -> Inscrito / Descartado
     */
    public function seedAcademic(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        // 1. No Contactado (Inicial)
        $noContactado = CustomStatus::create([
            'organization_id' => $organization->id,
            'name' => 'No Contactado',
            'slug' => 'no-contactado',
            'color' => '#3b82f6',
            'icon' => 'sym_r_mark_chat_unread',
            'stage_type' => 'initial',
            'is_default' => true,
            'sort_order' => 1,
        ]);

        // 2. Contactado (En Proceso - Depende de No Contactado)
        $contactado = CustomStatus::create([
            'organization_id' => $organization->id,
            'name' => 'Contactado',
            'slug' => 'contactado',
            'color' => '#06b6d4',
            'icon' => 'sym_r_forum',
            'stage_type' => 'in_progress',
            'is_default' => false,
            'sort_order' => 2,
        ]);
        $contactado->allowedPreviousStatuses()->sync([$noContactado->id]);

        // 3. Interesado / En Seguimiento (En Proceso - Depende de Contactado)
        $interesado = CustomStatus::create([
            'organization_id' => $organization->id,
            'name' => 'Interesado / En Seguimiento',
            'slug' => 'interesado-en-seguimiento',
            'color' => '#f59e0b',
            'icon' => 'sym_r_school',
            'stage_type' => 'in_progress',
            'is_default' => false,
            'sort_order' => 3,
        ]);
        $interesado->allowedPreviousStatuses()->sync([$contactado->id]);

        // 4. Inscrito (Ganado - Depende de Contactado o Interesado)
        $inscrito = CustomStatus::create([
            'organization_id' => $organization->id,
            'name' => 'Inscrito',
            'slug' => 'inscrito',
            'color' => '#10b981',
            'icon' => 'sym_r_check_circle',
            'stage_type' => 'won',
            'is_default' => false,
            'sort_order' => 4,
        ]);
        $inscrito->allowedPreviousStatuses()->sync([$contactado->id, $interesado->id]);

        // 5. No Interesado / Descartado (Perdido - Permitido desde etapas activas)
        $descartado = CustomStatus::create([
            'organization_id' => $organization->id,
            'name' => 'No Interesado / Descartado',
            'slug' => 'no-interesado-descartado',
            'color' => '#ef4444',
            'icon' => 'sym_r_cancel',
            'stage_type' => 'lost',
            'is_default' => false,
            'sort_order' => 5,
        ]);
        $descartado->allowedPreviousStatuses()->sync([$noContactado->id, $contactado->id, $interesado->id]);

        return response()->json([
            'message' => 'Flujo de estados universitarios cargado exitosamente.',
        ]);
    }
}
