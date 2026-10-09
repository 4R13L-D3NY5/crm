<?php

namespace App\Modules\Parameters\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Parameters\Models\TimeAlertRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimeAlertRuleController extends Controller
{
    /**
     * Listar todas las reglas de tiempo de inactividad de la organización.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $rules = TimeAlertRule::where('organization_id', $orgId)
            ->orderBy('created_at', 'desc')
            ->get();

        // Si la organización no tiene ninguna regla, autogenerar la regla estándar por defecto
        if ($rules->isEmpty()) {
            $defaultRule = TimeAlertRule::create([
                'organization_id' => $orgId,
                'name' => 'Regla General de Inactividad (SLA)',
                'description' => 'Alerta estándar de monitoreo de tiempos de espera para asesores y clientes.',
                'is_active' => true,
                'user_timeout_minutes' => 20,
                'client_timeout_minutes' => 120,
                'notify_user_inactivity' => true,
                'notify_client_inactivity' => true,
                'apply_to_all_statuses' => true,
                'custom_status_ids' => [],
                'severity' => 'warning',
                'action_type' => 'visual_badge',
            ]);

            $rules = collect([$defaultRule]);
        }

        // Obtener mapa de estados de la organización para enriquecer la respuesta
        $statuses = CustomStatus::where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get()
            ->keyBy('id');

        $formattedRules = $rules->map(function (TimeAlertRule $rule) use ($statuses) {
            $assignedStatuses = [];
            if (!$rule->apply_to_all_statuses && !empty($rule->custom_status_ids)) {
                foreach ($rule->custom_status_ids as $statusId) {
                    if (isset($statuses[$statusId])) {
                        $st = $statuses[$statusId];
                        $assignedStatuses[] = [
                            'id' => $st->id,
                            'name' => $st->name,
                            'color' => $st->color,
                            'icon' => $st->icon,
                            'stage_type' => $st->stage_type,
                        ];
                    }
                }
            }

            return array_merge($rule->toArray(), [
                'assigned_statuses' => $assignedStatuses,
            ]);
        });

        return response()->json([
            'data' => $formattedRules,
        ]);
    }

    /**
     * Crear una nueva regla de alerta de tiempo.
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'user_timeout_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'client_timeout_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'notify_user_inactivity' => ['nullable', 'boolean'],
            'notify_client_inactivity' => ['nullable', 'boolean'],
            'apply_to_all_statuses' => ['nullable', 'boolean'],
            'custom_status_ids' => ['nullable', 'array'],
            'custom_status_ids.*' => ['string'],
            'severity' => ['nullable', 'in:info,warning,critical'],
            'action_type' => ['nullable', 'in:visual_badge,notification,reassign'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $rule = TimeAlertRule::create([
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'user_timeout_minutes' => $validated['user_timeout_minutes'],
            'client_timeout_minutes' => $validated['client_timeout_minutes'],
            'notify_user_inactivity' => $validated['notify_user_inactivity'] ?? true,
            'notify_client_inactivity' => $validated['notify_client_inactivity'] ?? true,
            'apply_to_all_statuses' => $validated['apply_to_all_statuses'] ?? true,
            'custom_status_ids' => ($validated['apply_to_all_statuses'] ?? true) ? [] : ($validated['custom_status_ids'] ?? []),
            'severity' => $validated['severity'] ?? 'warning',
            'action_type' => $validated['action_type'] ?? 'visual_badge',
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'Regla de tiempo creada exitosamente.',
            'data' => $rule,
        ], 201);
    }

    /**
     * Actualizar una regla existente.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $rule = TimeAlertRule::where('organization_id', $orgId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:500'],
            'user_timeout_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'client_timeout_minutes' => ['required', 'integer', 'min:1', 'max:10080'],
            'notify_user_inactivity' => ['nullable', 'boolean'],
            'notify_client_inactivity' => ['nullable', 'boolean'],
            'apply_to_all_statuses' => ['nullable', 'boolean'],
            'custom_status_ids' => ['nullable', 'array'],
            'custom_status_ids.*' => ['string'],
            'severity' => ['nullable', 'in:info,warning,critical'],
            'action_type' => ['nullable', 'in:visual_badge,notification,reassign'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $applyAll = $validated['apply_to_all_statuses'] ?? $rule->apply_to_all_statuses;

        $rule->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'user_timeout_minutes' => $validated['user_timeout_minutes'],
            'client_timeout_minutes' => $validated['client_timeout_minutes'],
            'notify_user_inactivity' => $validated['notify_user_inactivity'] ?? $rule->notify_user_inactivity,
            'notify_client_inactivity' => $validated['notify_client_inactivity'] ?? $rule->notify_client_inactivity,
            'apply_to_all_statuses' => $applyAll,
            'custom_status_ids' => $applyAll ? [] : ($validated['custom_status_ids'] ?? []),
            'severity' => $validated['severity'] ?? $rule->severity,
            'action_type' => $validated['action_type'] ?? $rule->action_type,
            'is_active' => $validated['is_active'] ?? $rule->is_active,
        ]);

        return response()->json([
            'message' => 'Regla de tiempo actualizada correctamente.',
            'data' => $rule,
        ]);
    }

    /**
     * Alternar estado de activación rápida.
     */
    public function toggleActive(Request $request, string $id): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $rule = TimeAlertRule::where('organization_id', $orgId)->findOrFail($id);
        $rule->is_active = !$rule->is_active;
        $rule->save();

        return response()->json([
            'message' => 'Estado de la regla modificado correctamente.',
            'data' => $rule,
        ]);
    }

    /**
     * Eliminar una regla.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $rule = TimeAlertRule::where('organization_id', $orgId)->findOrFail($id);
        $rule->delete();

        return response()->json([
            'message' => 'Regla de tiempo eliminada exitosamente.',
        ]);
    }

    /**
     * Restablecer regla por defecto recomendada.
     */
    public function seedDefault(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $rule = TimeAlertRule::create([
            'organization_id' => $orgId,
            'name' => 'Regla General de Inactividad (SLA)',
            'description' => 'Alerta estándar de monitoreo de tiempos de espera para asesores y clientes.',
            'is_active' => true,
            'user_timeout_minutes' => 20,
            'client_timeout_minutes' => 120,
            'notify_user_inactivity' => true,
            'notify_client_inactivity' => true,
            'apply_to_all_statuses' => true,
            'custom_status_ids' => [],
            'severity' => 'warning',
            'action_type' => 'visual_badge',
        ]);

        return response()->json([
            'message' => 'Regla estándar generada con éxito.',
            'data' => $rule,
        ], 201);
    }
}
