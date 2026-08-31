<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Modules\WhatsApp\Actions\UpsertWhatsAppAccountAction;
use App\Modules\WhatsApp\Http\Requests\UpsertWhatsAppAccountRequest;
use App\Modules\WhatsApp\Http\Resources\WhatsAppAccountResource;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;

class WhatsAppAccountController
{
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
}
