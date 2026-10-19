<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Actions\UpsertWhatsAppAccountAction;
use App\Modules\WhatsApp\Http\Requests\UpsertWhatsAppAccountRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppAccountResource;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;

class WhatsAppAccountController
{
    public function index(): JsonResponse

    {
        $organization = request()->user()->currentOrganization;
        $accounts = WhatsAppAccount::query()
            ->with('authorizedUsers')
            ->where('organization_id', $organization->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => WhatsAppAccountResource::collection($accounts)->resolve(),
        ]);
    }

    public function store(\Illuminate\Http\Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $sessionType = $request->input('session_type', 'baileys_qr');
        $isSocial = in_array($sessionType, ['facebook', 'instagram', 'tiktok']);

        if ($isSocial) {
            $currentSocialCount = WhatsAppAccount::query()
                ->where('organization_id', $organization->id)
                ->whereIn('session_type', ['facebook', 'instagram', 'tiktok'])
                ->count();
            $maxSocial = $organization->max_social_accounts ?? 8;

            if ($currentSocialCount >= $maxSocial) {
                return response()->json([
                    'message' => "Has alcanzado el límite máximo de {$maxSocial} cuenta(s) de redes sociales de tu Plan " . ucfirst($organization->plan ?? 'básico') . ". Puedes añadir redes adicionales por +$10 USD/mes.",
                    'code' => 'QUOTA_SOCIAL_EXCEEDED',
                    'current_count' => $currentSocialCount,
                    'max_allowed' => $maxSocial,
                ], 422);
            }
        } else {
            $currentWhatsAppCount = WhatsAppAccount::query()
                ->where('organization_id', $organization->id)
                ->where(function ($q) {
                    $q->whereIn('session_type', ['baileys_qr', 'meta_cloud', 'qr_baileys', 'qr'])
                        ->orWhereNull('session_type');
                })
                ->count();
            $maxLines = $organization->max_whatsapp_lines ?? 1;

            if ($currentWhatsAppCount >= $maxLines) {
                return response()->json([
                    'message' => "Has alcanzado el límite máximo de {$maxLines} número(s) de WhatsApp de tu suscripción (Plan " . ucfirst($organization->plan ?? 'básico') . "). Contacta a soporte para habilitar números adicionales.",
                    'code' => 'QUOTA_CHANNELS_EXCEEDED',
                    'current_count' => $currentWhatsAppCount,
                    'max_allowed' => $maxLines,
                ], 422);
            }
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'session_type' => ['nullable', 'string', 'in:baileys_qr,meta_cloud,facebook,instagram,tiktok'],
            'display_phone_number' => ['nullable', 'string', 'max:100'],
            'phone_number_id' => ['nullable', 'string', 'max:255'],
            'business_account_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
            'verify_token' => ['nullable', 'string', 'max:255'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        $sessionType = $validated['session_type'] ?? 'baileys_qr';

        if (!empty($validated['access_token']) && (str_contains($validated['access_token'], 'SQLSTATE') || str_contains($validated['access_token'], 'ERROR:'))) {
            return response()->json([
                'message' => 'El Access Token ingresado no es válido. Debes pegar el Page Access Token generado en Meta (inicia con EAA...).',
            ], 422);
        }

        $hasCredentials = filled($validated['access_token'] ?? null) || filled($validated['phone_number_id'] ?? null);

        // Resolver phone_number_id sin colisiones para sesiones QR
        if ($sessionType === 'baileys_qr') {
            // Para Baileys QR, el phone_number_id interno es un identificador único de sesión
            // para evitar colisiones UNIQUE en la base de datos con el número telefónico visible
            $phoneNumberId = 'baileys_' . (string) \Illuminate\Support\Str::ulid();
        } else {
            $phoneNumberId = $validated['phone_number_id'] ?? ($validated['business_account_id'] ?? ($sessionType . '_' . (string) \Illuminate\Support\Str::ulid()));
        }

        // Si ya existe otra cuenta con este phone_number_id (ej. Meta Cloud API), responder 422 amigable
        if (WhatsAppAccount::where('phone_number_id', $phoneNumberId)->exists()) {
            return response()->json([
                'message' => 'El identificador de canal ingresado ya se encuentra registrado en el sistema.',
            ], 422);
        }

        try {
            $account = WhatsAppAccount::create([
                'organization_id' => $organization->id,
                'name' => $validated['name'],
                'session_type' => $sessionType,
                'daily_limit' => $validated['daily_limit'] ?? ($sessionType === 'baileys_qr' ? 100 : 1000),
                'display_phone_number' => $validated['display_phone_number'] ?? null,
                'phone_number_id' => $phoneNumberId,
                'business_account_id' => $validated['business_account_id'] ?? null,
                'access_token' => $validated['access_token'] ?? null,
                'verify_token' => $validated['verify_token'] ?? \Illuminate\Support\Str::random(32),
                'status' => ($sessionType !== 'baileys_qr' && $hasCredentials) ? 'CONNECTED' : 'DISCONNECTED',
                'is_active' => ($sessionType !== 'baileys_qr' && $hasCredentials),
                'last_connected_at' => ($sessionType !== 'baileys_qr' && $hasCredentials) ? now() : null,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23505') {
                return response()->json([
                    'message' => 'Ya existe un canal registrado con este número o identificador en el sistema.',
                ], 422);
            }
            throw $e;
        }

        return response()->json([
            'data' => (new WhatsAppAccountResource($account))->resolve(),
            'message' => 'Canal de comunicación registrado exitosamente.',
        ], 201);
    }


    public function show(): JsonResponse
    {
        $account = WhatsAppAccount::query()
            ->where('organization_id', request()->user()->current_organization_id)
            ->first();

        return response()->json([
            'data' => $account ? (new WhatsAppAccountResource($account))->resolve() : null,
        ]);
    }

    public function update(
        UpsertWhatsAppAccountRequest $request,
        UpsertWhatsAppAccountAction $upsertWhatsAppAccountAction,
    ): JsonResponse {
        $account = $upsertWhatsAppAccountAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => (new WhatsAppAccountResource($account))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $organization = request()->user()->currentOrganization;
        $account = WhatsAppAccount::where('organization_id', $organization->id)->findOrFail($id);
        $account->delete();

        return response()->json([
            'message' => 'Canal de WhatsApp eliminado exitosamente.',
        ]);
    }

    public function updateLimit(\Illuminate\Http\Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $account = WhatsAppAccount::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'daily_limit' => ['required', 'integer', 'min:1', 'max:10000'],
        ]);

        $account->update([
            'daily_limit' => $validated['daily_limit'],
        ]);

        return response()->json([
            'data' => (new WhatsAppAccountResource($account))->resolve(),
            'message' => 'Límite de seguridad Anti-Ban actualizado correctamente.',
        ]);
    }

    public function updateAccount(\Illuminate\Http\Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $account = WhatsAppAccount::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'display_phone_number' => ['nullable', 'string', 'max:100'],
            'daily_limit' => ['nullable', 'integer', 'min:1', 'max:10000'],
            'phone_number_id' => ['nullable', 'string', 'max:255'],
            'business_account_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
        ]);

        $updateData = [
            'name' => $validated['name'],
        ];

        if (array_key_exists('display_phone_number', $validated)) {
            $updateData['display_phone_number'] = $validated['display_phone_number'];
        }

        if (array_key_exists('daily_limit', $validated) && $validated['daily_limit'] !== null) {
            $updateData['daily_limit'] = $validated['daily_limit'];
        }

        if (!empty($validated['phone_number_id'])) {
            $updateData['phone_number_id'] = $validated['phone_number_id'];
        }

        if (array_key_exists('business_account_id', $validated)) {
            $updateData['business_account_id'] = $validated['business_account_id'];
        }

        if (!empty($validated['access_token'])) {
            $updateData['access_token'] = $validated['access_token'];
        }

        $account->update($updateData);

        return response()->json([
            'data' => (new WhatsAppAccountResource($account->fresh()))->resolve(),
            'message' => 'Canal actualizado exitosamente.',
        ]);
    }
}

