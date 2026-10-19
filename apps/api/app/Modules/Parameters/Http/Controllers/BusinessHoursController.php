<?php

namespace App\Modules\Parameters\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessHoursController extends Controller
{
    /**
     * Listar todas las configuraciones/perfiles de horario laboral de la organización.
     */
    public function index(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $configs = BusinessHoursConfig::where('organization_id', $orgId)
            ->with(['users:id,name,email'])
            ->orderByDesc('is_default')
            ->orderBy('created_at')
            ->get();

        if ($configs->isEmpty()) {
            $default = BusinessHoursConfig::create([
                'organization_id' => $orgId,
                'name' => 'Horario Laboral General',
                'scope_type' => 'general',
                'is_active' => true,
                'is_default' => true,
                'validity_type' => 'immediate',
                'timezone' => 'America/La_Paz',
                'schedule_days' => BusinessHoursConfig::defaultScheduleDays(),
                'auto_reply_enabled' => true,
                'auto_reply_message' => BusinessHoursConfig::defaultAutoReplyMessage(),
            ]);
            $configs = collect([$default->load('users:id,name,email')]);
        }

        $now = Carbon::now('America/La_Paz');

        // Obtener lista de operadores de la organización
        $operators = User::whereHas('organizations', fn($q) => $q->where('organizations.id', $orgId))
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $configs,
            'operators' => $operators,
            'meta' => [
                'current_day' => strtolower($now->format('l')),
                'current_time' => $now->format('H:i'),
                'current_date' => $now->toDateString(),
                'timezone' => 'America/La_Paz',
            ],
        ]);
    }

    /**
     * Obtener una configuración específica o la principal por defecto.
     */
    public function show(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)
            ->where('scope_type', 'general')
            ->orderByDesc('is_default')
            ->first();

        if (!$config) {
            $config = BusinessHoursConfig::firstOrCreate(
                ['organization_id' => $orgId],
                [
                    'name' => 'Horario Laboral General',
                    'scope_type' => 'general',
                    'is_active' => true,
                    'is_default' => true,
                    'validity_type' => 'immediate',
                    'timezone' => 'America/La_Paz',
                    'schedule_days' => BusinessHoursConfig::defaultScheduleDays(),
                    'auto_reply_enabled' => true,
                    'auto_reply_message' => BusinessHoursConfig::defaultAutoReplyMessage(),
                ]
            );
        }

        $config->load('users:id,name,email');
        $now = Carbon::now($config->timezone ?: 'America/La_Paz');

        return response()->json([
            'data' => $config,
            'meta' => [
                'is_within_hours' => $config->isWithinHours($now),
                'current_day' => strtolower($now->format('l')),
                'current_time' => $now->format('H:i'),
                'current_date' => $now->toDateString(),
                'timezone' => $config->timezone,
            ],
        ]);
    }

    /**
     * Crear una nueva configuración de horario laboral (Campaña, Temporada, Turno u Operador).
     */
    public function store(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'scope_type' => ['required', 'in:general,operator'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'validity_type' => ['required', 'in:immediate,date_range'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'schedule_days' => ['required', 'array'],
            'auto_reply_enabled' => ['required', 'boolean'],
            'auto_reply_message' => ['required', 'string', 'max:1500'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['string', 'exists:users,id'],
        ]);

        if (!empty($validated['is_default'])) {
            BusinessHoursConfig::where('organization_id', $orgId)
                ->where('scope_type', 'general')
                ->update(['is_default' => false]);
        }

        $config = BusinessHoursConfig::create([
            'organization_id' => $orgId,
            'name' => $validated['name'],
            'scope_type' => $validated['scope_type'],
            'is_active' => $validated['is_active'] ?? true,
            'is_default' => $validated['is_default'] ?? false,
            'validity_type' => $validated['validity_type'],
            'start_date' => $validated['validity_type'] === 'date_range' ? ($validated['start_date'] ?? null) : null,
            'end_date' => $validated['validity_type'] === 'date_range' ? ($validated['end_date'] ?? null) : null,
            'timezone' => $validated['timezone'] ?? 'America/La_Paz',
            'schedule_days' => $validated['schedule_days'],
            'auto_reply_enabled' => $validated['auto_reply_enabled'],
            'auto_reply_message' => $validated['auto_reply_message'],
        ]);

        if ($validated['scope_type'] === 'operator' && !empty($validated['user_ids'])) {
            $config->users()->sync($validated['user_ids']);
        }

        $config->load('users:id,name,email');

        return response()->json([
            'message' => 'Configuración de horario laboral creada exitosamente.',
            'data' => $config,
        ], 201);
    }

    /**
     * Actualizar configuración por ID.
     */
    public function updateConfig(Request $request, string $id): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'scope_type' => ['nullable', 'in:general,operator'],
            'is_active' => ['nullable', 'boolean'],
            'is_default' => ['nullable', 'boolean'],
            'validity_type' => ['nullable', 'in:immediate,date_range'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'schedule_days' => ['required', 'array'],
            'auto_reply_enabled' => ['required', 'boolean'],
            'auto_reply_message' => ['required', 'string', 'max:1500'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['string', 'exists:users,id'],
        ]);

        if (!empty($validated['is_default'])) {
            BusinessHoursConfig::where('organization_id', $orgId)
                ->where('id', '!=', $id)
                ->where('scope_type', 'general')
                ->update(['is_default' => false]);
        }

        $validityType = $validated['validity_type'] ?? $config->validity_type ?? 'immediate';

        $config->update([
            'name' => $validated['name'],
            'scope_type' => $validated['scope_type'] ?? $config->scope_type ?? 'general',
            'is_active' => $validated['is_active'] ?? $config->is_active,
            'is_default' => $validated['is_default'] ?? $config->is_default,
            'validity_type' => $validityType,
            'start_date' => $validityType === 'date_range' ? ($validated['start_date'] ?? $config->start_date) : null,
            'end_date' => $validityType === 'date_range' ? ($validated['end_date'] ?? $config->end_date) : null,
            'timezone' => $validated['timezone'] ?? $config->timezone,
            'schedule_days' => $validated['schedule_days'],
            'auto_reply_enabled' => $validated['auto_reply_enabled'],
            'auto_reply_message' => $validated['auto_reply_message'],
        ]);

        if (($validated['scope_type'] ?? $config->scope_type) === 'operator') {
            $config->users()->sync($validated['user_ids'] ?? []);
        } else {
            $config->users()->detach();
        }

        $config->load('users:id,name,email');
        $now = Carbon::now($config->timezone ?: 'America/La_Paz');

        return response()->json([
            'message' => 'Configuración de horario laboral actualizada exitosamente.',
            'data' => $config,
            'meta' => [
                'is_within_hours' => $config->isWithinHours($now),
                'current_day' => strtolower($now->format('l')),
                'current_time' => $now->format('H:i'),
                'current_date' => $now->toDateString(),
            ],
        ]);
    }

    /**
     * Eliminar una configuración de horario.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)->findOrFail($id);

        $totalConfigs = BusinessHoursConfig::where('organization_id', $orgId)->count();
        if ($totalConfigs <= 1) {
            return response()->json([
                'message' => 'No puedes eliminar la única configuración de horario existente.',
            ], 422);
        }

        $config->users()->detach();
        $config->delete();

        return response()->json([
            'message' => 'Configuración de horario eliminada exitosamente.',
        ]);
    }

    /**
     * Alternar estado activo de una configuración específica.
     */
    public function toggleConfigActive(Request $request, string $id): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)->findOrFail($id);
        $config->is_active = !$config->is_active;
        $config->save();

        return response()->json([
            'message' => $config->is_active ? 'Horario activado.' : 'Horario pausado.',
            'data' => $config->load('users:id,name,email'),
        ]);
    }

    /**
     * Alternar estado de activación general del horario laboral.
     */
    public function toggleActive(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)->orderByDesc('is_default')->first();
        if (!$config) {
            $config = BusinessHoursConfig::firstOrCreate(['organization_id' => $orgId]);
        }

        $config->is_active = !$config->is_active;
        $config->save();

        return response()->json([
            'message' => $config->is_active ? 'Horario laboral activado.' : 'Horario laboral pausado.',
            'data' => $config,
        ]);
    }

    /**
     * Alternar envío de respuesta automática fuera de horario laboral.
     */
    public function toggleAutoReply(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)->orderByDesc('is_default')->first();
        if (!$config) {
            $config = BusinessHoursConfig::firstOrCreate(['organization_id' => $orgId]);
        }

        $config->auto_reply_enabled = !$config->auto_reply_enabled;
        $config->save();

        return response()->json([
            'message' => $config->auto_reply_enabled ? 'Auto-respuesta fuera de horario activada.' : 'Auto-respuesta desactivada.',
            'data' => $config,
        ]);
    }

    /**
     * Actualizar la configuración predeterminada (compatibilidad con frontend anterior).
     */
    public function update(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)
            ->where('scope_type', 'general')
            ->orderByDesc('is_default')
            ->first();

        if (!$config) {
            $config = BusinessHoursConfig::create([
                'organization_id' => $orgId,
                'name' => 'Horario Laboral General',
                'scope_type' => 'general',
                'is_active' => true,
                'is_default' => true,
                'validity_type' => 'immediate',
                'timezone' => 'America/La_Paz',
                'schedule_days' => BusinessHoursConfig::defaultScheduleDays(),
                'auto_reply_enabled' => true,
                'auto_reply_message' => BusinessHoursConfig::defaultAutoReplyMessage(),
            ]);
        }

        return $this->updateConfig($request, $config->id);
    }

    /**
     * Consulta rápida de estado del horario laboral.
     */
    public function status(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)->where('is_active', true)->orderByDesc('is_default')->first();

        if (!$config) {
            return response()->json([
                'is_active' => false,
                'is_within_hours' => true,
                'auto_reply_enabled' => false,
            ]);
        }

        $now = Carbon::now($config->timezone ?: 'America/La_Paz');
        $isWithin = $config->isWithinHours($now);

        return response()->json([
            'is_active' => $config->is_active,
            'is_within_hours' => $isWithin,
            'auto_reply_enabled' => $config->auto_reply_enabled,
            'auto_reply_message' => (!$isWithin && $config->auto_reply_enabled) ? $config->auto_reply_message : null,
            'timezone' => $config->timezone,
            'current_day' => strtolower($now->format('l')),
            'current_time' => $now->format('H:i'),
        ]);
    }
}
