<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurrentUserController
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('organizations', 'currentOrganization');

        return response()->json([
            'data' => (new UserResource($user))->resolve(),
        ]);
    }
}
