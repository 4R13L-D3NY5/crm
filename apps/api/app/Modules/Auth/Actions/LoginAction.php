<?php

namespace App\Modules\Auth\Actions;

use App\Models\User;
use App\Modules\Auth\Exceptions\SessionConflictException;
use App\Modules\Auth\Support\DeviceDetector;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LoginAction
{
    public function execute(Request $request, array $credentials): User
    {
        $loginCredentials = [
            'email' => $credentials['email'],
            'password' => $credentials['password'],
        ];

        // 1. Validar credenciales sin iniciar sesión aún
        if (! Auth::validate($loginCredentials)) {
            throw new AuthenticationException('Credenciales invalidas.');
        }

        /** @var User $user */
        $user = User::where('email', $credentials['email'])->firstOrFail();

        $force = (bool) ($credentials['force'] ?? false);
        $currentSessionId = $request->session()->getId();

        // 2. Control de concurrencia: 1 dispositivo activo a la vez
        // Verificamos si existe la tabla 'sessions' y si hay sesiones activas en los últimos X minutos
        if (config('session.driver') === 'database' && Schema::hasTable('sessions')) {
            $lifetimeMinutes = (int) config('session.lifetime', 120);
            $cutoff = Carbon::now()->subMinutes($lifetimeMinutes)->timestamp;

            $activeSessions = DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $currentSessionId)
                ->where('last_activity', '>=', $cutoff)
                ->orderByDesc('last_activity')
                ->get();

            if ($activeSessions->isNotEmpty() && ! $force) {
                $mostRecent = $activeSessions->first();
                throw new SessionConflictException(
                    DeviceDetector::formatSession($mostRecent),
                    'Ya existe una sesión activa para esta cuenta en otro dispositivo.'
                );
            }

            // Si force es true o hay sesiones previas a revocar, registrar marca en caché y purgarlas
            if ($activeSessions->isNotEmpty()) {
                foreach ($activeSessions as $active) {
                    Cache::put("revoked_session:{$active->id}", 'another_device', now()->addHours(2));
                }

                DB::table('sessions')
                    ->where('user_id', $user->id)
                    ->where('id', '!=', $currentSessionId)
                    ->delete();
            }
        }

        // 3. Iniciar sesión oficialmente y regenerar ID de sesión
        Auth::login($user, true);
        $request->session()->regenerate();

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
