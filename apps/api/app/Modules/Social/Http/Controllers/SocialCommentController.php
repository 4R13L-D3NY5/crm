<?php

namespace App\Modules\Social\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Social\Models\SocialComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class SocialCommentController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $comments = SocialComment::where('organization_id', $organization->id)
            ->when($request->filled('platform') && $request->input('platform') !== 'all', function ($q) use ($request) {
                $q->where('platform', $request->input('platform'));
            })
            ->with(['contact', 'conversation'])
            ->latest()
            ->get();

        return response()->json(['data' => $comments]);
    }

    public function handleWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'string', 'exists:organizations,id'],
            'platform' => ['required', 'string', 'in:facebook,instagram,tiktok'],
            'post_id' => ['required', 'string'],
            'comment_id' => ['required', 'string'],
            'author_name' => ['required', 'string'],
            'author_id' => ['nullable', 'string'],
            'comment_text' => ['required', 'string'],
            'auto_reply_message' => ['nullable', 'string'],
            'open_dm_ticket' => ['boolean'],
        ]);

        $orgId = $validated['organization_id'];

        // 1. Crear o encontrar contacto por nombre / autor social
        $contact = Contact::firstOrCreate(
            ['organization_id' => $orgId, 'first_name' => $validated['author_name']],
            ['status' => 'active', 'notes' => "Lead capturado desde {$validated['platform']} Post #{$validated['post_id']}"]
        );

        $conversation = null;
        if ($validated['open_dm_ticket'] ?? true) {
            // 2. Abrir ticket DM en la bandeja de entrada
            $conversation = Conversation::create([
                'organization_id' => $orgId,
                'contact_id' => $contact->id,
                'channel' => $validated['platform'],
                'status' => 'pending',
                'last_message_at' => Carbon::now(),
                'unread_count' => 1,
            ]);

            // Mensaje entrante (el comentario original)
            Message::create([
                'organization_id' => $orgId,
                'conversation_id' => $conversation->id,
                'direction' => 'inbound',
                'body' => "💬 Comentario en publicación: \"{$validated['comment_text']}\"",
                'sent_at' => Carbon::now(),
            ]);
        }

        // 3. Registrar el comentario social
        $socialComment = SocialComment::create([
            'organization_id' => $orgId,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation?->id,
            'platform' => $validated['platform'],
            'post_id' => $validated['post_id'],
            'comment_id' => $validated['comment_id'],
            'author_name' => $validated['author_name'],
            'author_id' => $validated['author_id'] ?? null,
            'comment_text' => $validated['comment_text'],
            'reply_text' => $validated['auto_reply_message'] ?? '¡Hola! Te enviamos un mensaje privado con toda la información solicitada.',
            'is_replied' => true,
            'ticket_created' => (bool) $conversation,
        ]);

        return response()->json([
            'data' => $socialComment->load(['contact', 'conversation']),
            'message' => 'Comentario procesado: auto-respuesta pública enviada y ticket DM generado.',
        ], 201);
    }
}
