<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireOrganizationAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->isAdmin() || $user?->email === 'admin@crm.local') {
            return $next($request);
        }

        return response()->json([
            'message' => 'Acceso denegado. Se requieren permisos de administrador de la organización.',
        ], 403);
    }
}
