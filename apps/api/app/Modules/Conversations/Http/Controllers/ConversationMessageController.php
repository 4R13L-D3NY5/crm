<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Modules\Conversations\Actions\CreateInternalMessageAction;
use App\Modules\Conversations\Http\Requests\CreateInternalMessageRequest;
use App\Modules\Conversations\Http\Resources\MessageResource;
use App\Modules\Conversations\Models\Conversation;
use Illuminate\Http\JsonResponse;

class ConversationMessageController
{
    public function store(
        CreateInternalMessageRequest $request,
        Conversation $conversation,
        CreateInternalMessageAction $createInternalMessageAction,
    ): JsonResponse {
        $message = $createInternalMessageAction->execute(
            $request->user(),
            $conversation,
            $request->validated(),
        );

        return response()->json([
            'data' => (new MessageResource($message))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ], 201);
    }
}
