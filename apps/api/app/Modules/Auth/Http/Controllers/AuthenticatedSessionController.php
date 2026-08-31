<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Modules\Auth\Actions\LoginAction;
use App\Modules\Auth\Actions\LogoutAction;
use App\Modules\Auth\Http\Requests\LoginRequest;
use App\Modules\Auth\Http\Resources\UserResource;
use App\Shared\Actions\WriteAuditLogAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthenticatedSessionController
{
    public function store(
        LoginRequest $request,
        LoginAction $loginAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse
    {
        $user = $loginAction->execute($request, $request->validated());
        $writeAuditLogAction->execute(
            event: 'auth.login',
            user: $user,
            request: $request,
            metadata: [
                'email' => $user->email,
            ],
        );

        return response()->json([
            'data' => (new UserResource($user))->resolve(),
            'message' => 'Sesion iniciada correctamente.',
        ]);
    }

    public function destroy(
        Request $request,
        LogoutAction $logoutAction,
        WriteAuditLogAction $writeAuditLogAction,
    ): JsonResponse
    {
        if ($request->user() !== null) {
            $writeAuditLogAction->execute(
                event: 'auth.logout',
                user: $request->user(),
                request: $request,
                metadata: [
                    'email' => $request->user()->email,
                ],
            );
        }

        $logoutAction->execute($request);

        return response()->json([
            'message' => 'Sesion cerrada correctamente.',
        ]);
    }
}
