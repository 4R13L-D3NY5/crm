<?php

namespace App\Modules\Parameters\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusinessHoursController extends Controller
{
    /**
     * Obtener la configuración de horario laboral de la organización (autogenerando la base si no existe).
     */
    public function show(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::firstOrCreate(
            ['organization_id' => $orgId],
            [
                'name' => 'Horario Laboral General',
                'is_active' => true,
                'validity_type' => 'immediate',
                'start_date' => null,
                'end_date' => null,
                'timezone' => 'America/La_Paz',
                'schedule_days' => BusinessHoursConfig::defaultScheduleDays(),
                'auto_reply_enabled' => true,
                'auto_reply_message' => BusinessHoursConfig::defaultAutoReplyMessage(),
            ]
        );

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
     * Actualizar la configuración de horario laboral y auto-respuesta.
     */
    public function update(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'validity_type' => ['required', 'in:immediate,date_range'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'timezone' => ['nullable', 'string', 'max:60'],
            'schedule_days' => ['required', 'array'],
            'auto_reply_enabled' => ['required', 'boolean'],
            'auto_reply_message' => ['required', 'string', 'max:1500'],
        ]);

        $config = BusinessHoursConfig::firstOrCreate(['organization_id' => $orgId]);

        $config->update([
            'name' => $validated['name'] ?? $config->name,
            'is_active' => $validated['is_active'] ?? $config->is_active,
            'validity_type' => $validated['validity_type'],
            'start_date' => $validated['validity_type'] === 'date_range' ? ($validated['start_date'] ?? null) : null,
            'end_date' => $validated['validity_type'] === 'date_range' ? ($validated['end_date'] ?? null) : null,
            'timezone' => $validated['timezone'] ?? $config->timezone,
            'schedule_days' => $validated['schedule_days'],
            'auto_reply_enabled' => $validated['auto_reply_enabled'],
            'auto_reply_message' => $validated['auto_reply_message'],
        ]);

        $now = Carbon::now($config->timezone ?: 'America/La_Paz');

        return response()->json([
            'message' => 'Configuración de horario laboral actualizada exitosamente.',
            'data' => $config->fresh(),
            'meta' => [
                'is_within_hours' => $config->isWithinHours($now),
                'current_day' => strtolower($now->format('l')),
                'current_time' => $now->format('H:i'),
                'current_date' => $now->toDateString(),
            ],
        ]);
    }

    /**
     * Alternar estado de activación general del horario laboral.
     */
    public function toggleActive(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::firstOrCreate(['organization_id' => $orgId]);
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

        $config = BusinessHoursConfig::firstOrCreate(['organization_id' => $orgId]);
        $config->auto_reply_enabled = !$config->auto_reply_enabled;
        $config->save();

        return response()->json([
            'message' => $config->auto_reply_enabled ? 'Auto-respuesta fuera de horario activada.' : 'Auto-respuesta desactivada.',
            'data' => $config,
        ]);
    }

    /**
     * Consulta rápida de estado del horario laboral (para widgets o verificación de bots).
     */
    public function status(Request $request): JsonResponse
    {
        $orgId = $request->user()->current_organization_id;

        $config = BusinessHoursConfig::where('organization_id', $orgId)->first();

        if (!$config || !$config->is_active) {
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
