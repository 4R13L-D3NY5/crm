<?php

namespace App\Modules\QuickMessages\Http\Controllers;

use App\Modules\QuickMessages\Actions\CreateQuickMessageAction;
use App\Modules\QuickMessages\Actions\DeleteQuickMessageAction;
use App\Modules\QuickMessages\Actions\UpdateQuickMessageAction;
use App\Modules\QuickMessages\Http\Requests\CreateQuickMessageRequest;
use App\Modules\QuickMessages\Http\Requests\UpdateQuickMessageRequest;
use App\Modules\QuickMessages\Http\Resources\QuickMessageResource;
use App\Modules\QuickMessages\Models\QuickMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuickMessageController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $organizationId = $request->user()->current_organization_id;
        $userId = $request->user()->id;

        $quickMessages = QuickMessage::query()
            ->where('organization_id', $organizationId)
            ->where(function ($query) use ($userId) {
                $query->where('is_general', true)
                    ->orWhere('user_id', $userId);
            })
            ->orderBy('shortcut')
            ->get();

        return QuickMessageResource::collection($quickMessages);
    }

    public function store(
        CreateQuickMessageRequest $request,
        CreateQuickMessageAction $createQuickMessageAction,
    ): JsonResponse {
        $quickMessage = $createQuickMessageAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => (new QuickMessageResource($quickMessage))->resolve(),
            'message' => 'Respuesta rápida creada correctamente.',
        ], 201);
    }

    public function show(QuickMessage $quickMessage): JsonResponse
    {
        return response()->json([
            'data' => (new QuickMessageResource($quickMessage))->resolve(),
        ]);
    }

    public function update(
        UpdateQuickMessageRequest $request,
        QuickMessage $quickMessage,
        UpdateQuickMessageAction $updateQuickMessageAction,
    ): JsonResponse {
        $updated = $updateQuickMessageAction->execute(
            $quickMessage,
            $request->validated(),
        );

        return response()->json([
            'data' => (new QuickMessageResource($updated))->resolve(),
            'message' => 'Respuesta rápida actualizada correctamente.',
        ]);
    }

    public function destroy(QuickMessage $quickMessage, DeleteQuickMessageAction $deleteQuickMessageAction): JsonResponse
    {
        $deleteQuickMessageAction->execute($quickMessage);

        return response()->json([
            'message' => 'Respuesta rápida eliminada correctamente.',
        ]);
    }
}
