<?php

namespace App\Modules\Queues\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Queues\Models\BotFlow;
use App\Modules\Queues\Services\BotFlowEngineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BotFlowController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $flows = BotFlow::where('organization_id', $organization->id)
            ->with(['queue'])
            ->latest()
            ->get();

        return response()->json(['data' => $flows]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'queue_id' => ['nullable', 'string', 'exists:queues,id'],
            'trigger_keyword' => ['nullable', 'string', 'max:50'],
            'greeting_message' => ['required', 'string'],
            'options' => ['required', 'array'],
            'handoff_to_ai' => ['boolean'],
            'fallback_message' => ['nullable', 'string'],
        ]);

        $flow = BotFlow::create([
            'organization_id' => $organization->id,
            'queue_id' => $validated['queue_id'] ?? null,
            'name' => $validated['name'],
            'trigger_keyword' => $validated['trigger_keyword'] ?? null,
            'is_active' => true,
            'greeting_message' => $validated['greeting_message'],
            'options' => $validated['options'],
            'handoff_to_ai' => $validated['handoff_to_ai'] ?? true,
            'fallback_message' => $validated['fallback_message'] ?? null,
        ]);

        return response()->json([
            'data' => $flow->load('queue'),
            'message' => 'Flujo de chatbot creado con éxito.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $flow = BotFlow::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:150'],
            'queue_id' => ['nullable', 'string', 'exists:queues,id'],
            'trigger_keyword' => ['nullable', 'string', 'max:50'],
            'greeting_message' => ['sometimes', 'string'],
            'options' => ['sometimes', 'array'],
            'is_active' => ['sometimes', 'boolean'],
            'handoff_to_ai' => ['sometimes', 'boolean'],
            'fallback_message' => ['nullable', 'string'],
        ]);

        $flow->update($validated);

        return response()->json([
            'data' => $flow->load('queue'),
            'message' => 'Flujo de chatbot actualizado.',
        ]);
    }

    public function processMessage(
        Request $request,
        string $id,
        BotFlowEngineService $engine
    ): JsonResponse {
        $organization = $request->user()->currentOrganization;
        $flow = BotFlow::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'conversation_id' => ['required', 'string', 'exists:conversations,id'],
            'message_text' => ['required', 'string'],
        ]);

        $conversation = Conversation::where('organization_id', $organization->id)->findOrFail($validated['conversation_id']);

        $result = $engine->process($flow, $conversation, $validated['message_text']);

        return response()->json([
            'data' => $result,
            'message' => 'Mensaje evaluado por el motor de flujo.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $flow = BotFlow::where('organization_id', $organization->id)->findOrFail($id);

        $flow->delete();

        return response()->json([
            'message' => 'Flujo de chatbot eliminado.',
        ]);
    }
}
