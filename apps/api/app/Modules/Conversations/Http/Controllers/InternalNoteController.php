<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class InternalNoteController extends Controller
{
    public function store(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $ticket = Conversation::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $message = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $ticket->id,
            'direction' => 'outbound',
            'is_internal' => true,
            'body' => $validated['body'],
            'sent_at' => Carbon::now(),
        ]);

        return response()->json([
            'data' => $message,
            'message' => 'Nota interna agregada exitosamente.',
        ], 201);
    }
}
