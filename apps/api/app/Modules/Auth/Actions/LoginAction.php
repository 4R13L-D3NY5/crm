<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginAction
{
    public function execute(Request $request, array $credentials): User
    {
        if (! Auth::attempt($credentials, true)) {
            throw new AuthenticationException('Credenciales invalidas.');
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = Auth::user();
        $user->load('organizations', 'currentOrganization');

        if ($user->currentOrganization === null) {
            $organization = $user->organizations->first();

            if ($organization !== null) {
                $user->forceFill([
                    'current_organization_id' => $organization->getKey(),
                ])->save();

                $user->setRelation('currentOrganization', $organization);
            }
        }

        return $user->fresh(['organizations', 'currentOrganization']);
    }
}
