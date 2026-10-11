<?php

namespace App\Modules\HentleAi\Http\Controllers;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\HentleAi\Services\HentleAiCopilotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HentleAiCopilotController
{
    public function suggestReply(
        Request $request,
        Conversation $conversation,
        HentleAiCopilotService $copilotService,
    ): JsonResponse {
        $instruction = $request->input('instruction');

        try {
            $result = $copilotService->generateReplySuggestion($conversation, $instruction);

            return response()->json([
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function summarize(
        Conversation $conversation,
        HentleAiCopilotService $copilotService,
    ): JsonResponse {
        try {
            $result = $copilotService->summarizeTicket($conversation);

            return response()->json([
                'data' => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
