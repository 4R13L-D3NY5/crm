<?php

namespace App\Modules\Contacts\Policies;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;

class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('contacts.view');
    }

    public function view(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.view')
            && $user->current_organization_id === $contact->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->current_organization_id !== null
            && $user->hasPermission('contacts.manage');
    }

    public function update(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.manage')
            && $user->current_organization_id === $contact->organization_id;
    }

    public function delete(User $user, Contact $contact): bool
    {
        return $user->hasPermission('contacts.manage')
            && $user->current_organization_id === $contact->organization_id;
    }
}
