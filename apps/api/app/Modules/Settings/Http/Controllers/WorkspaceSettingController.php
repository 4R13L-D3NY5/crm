<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $settings = WorkspaceSetting::firstOrCreate(
            ['organization_id' => $organization->id],
            [
                'default_language' => 'es',
                'timezone' => 'America/La_Paz',
                'hide_contact_data' => false,
                'enforce_2fa' => false,
                'sla_timeout_minutes' => 10,
            ]
        );

        return response()->json(['data' => $settings]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'default_language' => ['sometimes', 'string', 'max:10'],
            'timezone' => ['sometimes', 'string', 'max:100'],
            'hide_contact_data' => ['sometimes', 'boolean'],
            'enforce_2fa' => ['sometimes', 'boolean'],
            'sla_timeout_minutes' => ['sometimes', 'integer', 'min:1', 'max:1440'],
            'custom_options' => ['sometimes', 'array'],
        ]);

        $organization = $request->user()->currentOrganization;

        $settings = WorkspaceSetting::updateOrCreate(
            ['organization_id' => $organization->id],
            $validated
        );

        return response()->json([
            'data' => $settings,
            'message' => 'Configuración del workspace actualizada exitosamente.',
        ]);
    }
}
