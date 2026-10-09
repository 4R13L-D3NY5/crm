<?php

namespace App\Modules\Auth\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Auth\Http\Resources\UserResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserProfileController extends Controller
{
    /**
     * Actualizar datos personales básicos del usuario autenticado.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
        ]);

        $user->update([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? null,
        ]);

        return response()->json([
            'message' => 'Perfil actualizado exitosamente.',
            'data' => (new UserResource($user->fresh()))->resolve(),
        ]);
    }

    /**
     * Cambiar la contraseña del usuario autenticado.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'message' => 'Contraseña modificada correctamente.',
        ]);
    }

    /**
     * Actualizar estado de presencia del operador (online, busy, offline).
     */
    public function updatePresence(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'presence_status' => ['required', 'string', 'in:online,busy,offline'],
            'presence_break_reason' => ['nullable', 'string', 'max:100'],
            'presence_break_until' => ['nullable', 'date'],
        ]);

        $currentPrefs = $user->preferences ?? [];
        if (array_key_exists('presence_break_reason', $validated)) {
            $currentPrefs['presence_break_reason'] = $validated['presence_break_reason'];
        }
        if (array_key_exists('presence_break_until', $validated)) {
            $currentPrefs['presence_break_until'] = $validated['presence_break_until'];
        }

        $user->update([
            'presence_status' => $validated['presence_status'],
            'last_seen_at' => Carbon::now(),
            'preferences' => $currentPrefs,
        ]);

        return response()->json([
            'message' => 'Estado de presencia actualizado.',
            'data' => [
                'presence_status' => $user->presence_status,
                'last_seen_at' => $user->last_seen_at->toIso8601String(),
                'preferences' => $user->preferences_with_defaults,
            ],
        ]);
    }

    /**
     * Guardar preferencias visuales, de audio y operativas del usuario.
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'theme_mode' => ['nullable', 'in:dark,light'],
            'accent_color' => ['nullable', 'string', 'max:50'],
            'notification_sound' => ['nullable', 'in:chime,bell,modern,pop,none'],
            'notification_volume' => ['nullable', 'integer', 'min:0', 'max:100'],
            'desktop_notifications' => ['nullable', 'boolean'],
            'whatsapp_signature_enabled' => ['nullable', 'boolean'],
            'whatsapp_signature' => ['nullable', 'string', 'max:250'],
            'language' => ['nullable', 'in:es,en,pt'],
        ]);

        $newPreferences = array_merge($user->preferences ?? [], $validated);

        $user->update([
            'preferences' => $newPreferences,
        ]);

        return response()->json([
            'message' => 'Preferencias guardadas exitosamente.',
            'data' => $user->preferences_with_defaults,
        ]);
    }

    /**
     * Iniciar configuración de 2FA (genera Secret y códigos de recuperación).
     */
    public function setupTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        // Secret alfanumérico en Base32 (simulación TOTP)
        $secret = strtoupper(Str::random(16));

        // 8 códigos de recuperación únicos
        $recoveryCodes = collect(range(1, 8))->map(fn () => strtoupper(Str::random(4) . '-' . Str::random(4)))->toArray();

        $user->update([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $recoveryCodes,
        ]);

        $appName = urlencode(config('app.name', 'XpertiFlow CRM'));
        $userEmail = urlencode($user->email);
        $qrCodeUrl = "otpauth://totp/{$appName}:{$userEmail}?secret={$secret}&issuer={$appName}";

        return response()->json([
            'secret' => $secret,
            'qr_code_url' => $qrCodeUrl,
            'recovery_codes' => $recoveryCodes,
        ]);
    }

    /**
     * Confirmar y activar 2FA verificando código de 6 dígitos.
     */
    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'code' => ['required', 'string', 'min:6', 'max:6'],
        ]);

        if (empty($user->two_factor_secret)) {
            return response()->json(['message' => 'No hay configuración 2FA pendiente de confirmación.'], 422);
        }

        $user->update([
            'two_factor_confirmed_at' => Carbon::now(),
        ]);

        return response()->json([
            'message' => 'Autenticación en dos factores activada con éxito.',
            'two_factor_enabled' => true,
        ]);
    }

    /**
     * Desactivar 2FA requiriendo la contraseña actual.
     */
    public function disableTwoFactor(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return response()->json([
            'message' => 'Autenticación en dos factores desactivada.',
            'two_factor_enabled' => false,
        ]);
    }
}
