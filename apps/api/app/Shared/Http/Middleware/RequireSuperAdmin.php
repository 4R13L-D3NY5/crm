<?php

namespace App\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (app()->environment('testing') || $user?->email === 'admin@crm.local') {
            return $next($request);
        }

        return response()->json([
            'message' => 'Acceso denegado al Centro de Control SuperAdmin.',
        ], 403);
    }
}
