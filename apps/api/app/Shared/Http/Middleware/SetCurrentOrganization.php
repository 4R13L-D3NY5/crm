<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentOrganization
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        $user->loadMissing('organizations', 'currentOrganization');

        if ($user->currentOrganization === null) {
            $organization = $user->organizations->first();

            if ($organization !== null) {
                $user->forceFill([
                    'current_organization_id' => $organization->getKey(),
                ])->save();

                $user->setRelation('currentOrganization', $organization);
            }
        }

        app()->instance('currentOrganization', $user->currentOrganization);
        $request->attributes->set('current_organization', $user->currentOrganization);

        return $next($request);
    }
}
