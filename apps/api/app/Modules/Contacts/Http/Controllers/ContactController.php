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

    private function authorize($request, string $ability, mixed $arguments): void
    {
        abort_unless($request->user()?->can($ability, $arguments), 403);
    }
}
