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
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'session_type' => ['nullable', 'string', 'in:baileys_qr,meta_cloud,facebook,instagram,tiktok'],
            'display_phone_number' => ['nullable', 'string', 'max:100'],
            'phone_number_id' => ['nullable', 'string', 'max:255'],
            'business_account_id' => ['nullable', 'string', 'max:255'],
            'access_token' => ['nullable', 'string'],
            'verify_token' => ['nullable', 'string', 'max:255'],
        ]);

        $sessionType = $validated['session_type'] ?? 'baileys_qr';
        $hasCredentials = filled($validated['access_token'] ?? null) || filled($validated['phone_number_id'] ?? null);

        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'session_type' => $sessionType,
            'display_phone_number' => $validated['display_phone_number'] ?? null,
            'phone_number_id' => $validated['phone_number_id'] ?? null,
            'business_account_id' => $validated['business_account_id'] ?? null,
            'access_token' => $validated['access_token'] ?? null,
            'verify_token' => $validated['verify_token'] ?? \Illuminate\Support\Str::random(32),
            'status' => ($sessionType !== 'baileys_qr' && $hasCredentials) ? 'CONNECTED' : 'DISCONNECTED',
            'is_active' => ($sessionType !== 'baileys_qr' && $hasCredentials),
            'last_connected_at' => ($sessionType !== 'baileys_qr' && $hasCredentials) ? now() : null,
        ]);

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
}

