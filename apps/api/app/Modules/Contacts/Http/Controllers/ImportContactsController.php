<?php

namespace App\Modules\Contacts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ImportContactsController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'contacts' => ['required', 'array', 'min:1'],
            'contacts.*.name' => ['required', 'string', 'max:200'],
            'contacts.*.phone' => ['required', 'string', 'max:50'],
            'contacts.*.email' => ['nullable', 'email', 'max:200'],
            'contacts.*.tags' => ['nullable', 'array'],
        ]);

        $createdCount = 0;
        $updatedCount = 0;

        foreach ($validated['contacts'] as $row) {
            $nameParts = explode(' ', trim($row['name']), 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? null;

            // Formatear o limpiar teléfono
            $cleanPhone = preg_replace('/[^0-9+]/', '', $row['phone']);

            $contact = Contact::updateOrCreate(
                [
                    'organization_id' => $organization->id,
                    'phone' => $cleanPhone,
                ],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $row['email'] ?? null,
                    'status' => 'active',
                ]
            );

            if ($contact->wasRecentlyCreated) {
                $createdCount++;
            } else {
                $updatedCount++;
            }

            // Asignar tags si vienen en el payload
            if (!empty($row['tags'])) {
                $tagIds = [];
                foreach ($row['tags'] as $tagName) {
                    $tag = Tag::firstOrCreate(
                        ['organization_id' => $organization->id, 'name' => trim($tagName)],
                        ['slug' => Str::slug(trim($tagName)) . '-' . Str::random(3), 'color_hex' => '#00a884']
                    );
                    $tagIds[] = $tag->id;
                }
                $contact->tags()->syncWithoutDetaching($tagIds);
            }
        }

        return response()->json([
            'data' => [
                'created_count' => $createdCount,
                'updated_count' => $updatedCount,
                'total_processed' => count($validated['contacts']),
            ],
            'message' => "Importación completada: {$createdCount} nuevos contactos creados, {$updatedCount} actualizados.",
        ]);
    }
}
