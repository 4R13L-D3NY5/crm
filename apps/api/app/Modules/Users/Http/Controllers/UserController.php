<?php

namespace App\Modules\Users\Http\Controllers;

use App\Models\User;
use App\Modules\Users\Http\Resources\UserOptionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController
{
    public function index(Request $request): JsonResponse
    {
        $organizationId = $request->user()->current_organization_id;

        $users = User::query()
            ->whereHas('organizations', fn ($query) => $query->where('organizations.id', $organizationId))
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => UserOptionResource::collection($users)->resolve(),
        ]);
    }
}
