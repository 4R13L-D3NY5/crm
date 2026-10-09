<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UpdateContactAction
{
    public function execute(Contact $contact, array $payload): Contact
    {
        $contact->update([
            'first_name' => $payload['first_name'],
            'last_name' => $payload['last_name'] ?? null,
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'status' => $payload['status'] ?? $contact->status,
            'custom_status_id' => array_key_exists('custom_status_id', $payload) ? $payload['custom_status_id'] : $contact->custom_status_id,
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->syncTags($contact, Arr::wrap($payload['tags'] ?? []));

        return $contact->load('tags');
    }

    private function syncTags(Contact $contact, array $tags): void
    {
        $tagIds = collect($tags)
            ->filter()
            ->map(function (string $name) use ($contact) {
                $tag = Tag::query()->firstOrCreate(
                    [
                        'organization_id' => $contact->organization_id,
                        'slug' => Str::slug($name),
                    ],
                    [
                        'name' => Str::title($name),
                    ],
                );

                return $tag->getKey();
            })
            ->values()
            ->all();

        $contact->tags()->sync($tagIds);
    }
}
