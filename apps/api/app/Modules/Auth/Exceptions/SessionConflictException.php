<?php

namespace App\Modules\Auth\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

class SessionConflictException extends Exception
{
    public function __construct(
        public readonly array $activeSession,
        string $message = 'Ya existe una sesión activa para esta cuenta en otro dispositivo.'
    ) {
        parent::__construct($message, 409);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'status' => 'session_conflict',
            'message' => $this->getMessage(),
            'active_session' => $this->activeSession,
        ], 409);
    }
}
