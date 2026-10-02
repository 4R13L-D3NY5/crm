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
            'session_type' => ['nullable', 'string', 'in:baileys_qr,meta_cloud'],
            'display_phone_number' => ['nullable', 'string', 'max:50'],
        ]);

        $account = WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => $validated['name'],
            'session_type' => $validated['session_type'] ?? 'baileys_qr',
            'display_phone_number' => $validated['display_phone_number'] ?? null,
            'status' => 'DISCONNECTED',
            'is_active' => false,
        ]);

        return response()->json([
            'data' => (new WhatsAppAccountResource($account))->resolve(),
            'message' => 'Canal de WhatsApp registrado exitosamente.',
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

