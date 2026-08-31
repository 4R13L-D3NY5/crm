<?php

namespace App\Modules\Contacts\Actions;

use App\Modules\Contacts\Models\Contact;

class DeleteContactAction
{
    public function execute(Contact $contact): void
    {
        $contact->delete();
    }
}
