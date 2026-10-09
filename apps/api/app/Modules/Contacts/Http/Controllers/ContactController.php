<?php

namespace App\Modules\Contacts\Http\Controllers;

use App\Modules\Contacts\Actions\CreateContactAction;
use App\Modules\Contacts\Actions\DeleteContactAction;
use App\Modules\Contacts\Actions\UpdateContactAction;
use App\Modules\Contacts\Http\Requests\CreateContactRequest;
use App\Modules\Contacts\Http\Requests\ListContactsRequest;
use App\Modules\Contacts\Http\Requests\UpdateContactRequest;
use App\Modules\Contacts\Http\Resources\ContactResource;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Queries\ListContactsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController
{
    public function index(
        ListContactsRequest $request,
        ListContactsQuery $listContactsQuery,
    ): AnonymousResourceCollection {
        $this->authorize($request, 'viewAny', Contact::class);

        $contacts = $listContactsQuery->execute(
            $request->user(),
            $request->validated(),
        );

        return ContactResource::collection($contacts);
    }

    public function store(
        CreateContactRequest $request,
        CreateContactAction $createContactAction,
    ): JsonResponse {
        $this->authorize($request, 'create', Contact::class);

        $contact = $createContactAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => (new ContactResource($contact))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ], 201);
    }

    public function show(Contact $contact): JsonResponse
    {
        $this->authorize(request(), 'view', $contact);

        return response()->json([
            'data' => (new ContactResource($contact->load([
                'tags',
                'companies',
                'deals.company',
                'conversations.assignment.assignee',
            ])))->resolve(),
        ]);
    }

    public function update(
        UpdateContactRequest $request,
        Contact $contact,
        UpdateContactAction $updateContactAction,
    ): JsonResponse {
        $this->authorize($request, 'update', $contact);

        $contact = $updateContactAction->execute($contact, $request->validated());

        return response()->json([
            'data' => (new ContactResource($contact))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ]);
    }

    public function destroy(Contact $contact, DeleteContactAction $deleteContactAction): JsonResponse
    {
        $this->authorize(request(), 'delete', $contact);

        $deleteContactAction->execute($contact);

        return response()->json([
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    public function bulkMessage(Request $request): JsonResponse
    {
        $this->authorize($request, 'viewAny', Contact::class);

        $validated = $request->validate([
            'contact_ids' => ['required', 'array', 'min:1'],
            'contact_ids.*' => ['required', 'string'],
            'message' => ['required', 'string', 'max:4000'],
            'whatsapp_account_id' => ['nullable', 'string', 'exists:whatsapp_accounts,id'],
        ]);

        $user = $request->user();
        $orgId = $user->current_organization_id;

        $account = null;
        if (!empty($validated['whatsapp_account_id'])) {
            $account = \App\Modules\WhatsApp\Models\WhatsAppAccount::where('organization_id', $orgId)
                ->find($validated['whatsapp_account_id']);
        }
        if (!$account) {
            $account = \App\Modules\WhatsApp\Models\WhatsAppAccount::where('organization_id', $orgId)
                ->where('is_active', true)
                ->where('status', 'CONNECTED')
                ->latest('last_connected_at')
                ->first()
                ?? \App\Modules\WhatsApp\Models\WhatsAppAccount::where('organization_id', $orgId)
                ->where('is_active', true)
                ->first();
        }

        $contacts = Contact::where('organization_id', $orgId)
            ->whereIn('id', $validated['contact_ids'])
            ->whereNotNull('phone')
            ->get();

        $sentCount = 0;
        foreach ($contacts as $contact) {
            $body = str_replace(
                ['{{nombre}}', '{{name}}', '{nombre}', '{name}'],
                $contact->name ?: 'Cliente',
                $validated['message']
            );
            $body = str_replace(
                ['{{telefono}}', '{{phone}}', '{telefono}', '{phone}'],
                $contact->phone ?: '',
                $body
            );

            $conversation = \App\Modules\Conversations\Models\Conversation::firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'contact_id' => $contact->id,
                    'channel' => 'whatsapp',
                ],
                [
                    'subject' => 'Mensaje Directo',
                    'status' => 'open',
                    'whatsapp_account_id' => $account?->id,
                    'last_message_at' => now(),
                ]
            );

            if ($account && !$conversation->whatsapp_account_id) {
                $conversation->whatsapp_account_id = $account->id;
                $conversation->save();
            }

            $msg = \App\Modules\Conversations\Models\Message::create([
                'organization_id' => $orgId,
                'conversation_id' => $conversation->id,
                'user_id' => $user->id,
                'direction' => 'outbound',
                'message_type' => 'text',
                'message_status' => 'pending',
                'body' => $body,
                'sent_at' => now(),
            ]);

            if ($account) {
                \App\Modules\WhatsApp\Models\WhatsAppMessageMapping::create([
                    'organization_id' => $orgId,
                    'whatsapp_account_id' => $account->id,
                    'contact_id' => $contact->id,
                    'conversation_id' => $conversation->id,
                    'message_id' => $msg->id,
                    'provider_message_id' => 'bulk_' . $msg->id,
                    'direction' => 'outbound',
                    'status' => 'pending',
                    'to_phone_number_id' => $account->phone_number_id ?? $account->id,
                ]);

                \App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob::dispatch($msg->id);
            }

            $conversation->update(['last_message_at' => now()]);
            $sentCount++;
        }

        return response()->json([
            'message' => "Se despachó el mensaje a {$sentCount} contactos seleccionados.",
            'sent_count' => $sentCount,
            'total_selected' => count($validated['contact_ids']),
        ]);
    }

    private function authorize($request, string $ability, mixed $arguments): void
    {
        abort_unless($request->user()?->can($ability, $arguments), 403);
    }
}
