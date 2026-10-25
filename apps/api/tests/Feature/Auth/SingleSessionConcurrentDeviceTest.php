<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SingleSessionConcurrentDeviceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::factory()->create();
        $this->user = User::factory()->create([
            'email' => 'operador@unitepc.edu.bo',
            'password' => Hash::make('Secret123!'),
            'current_organization_id' => $this->organization->getKey(),
        ]);

        $this->organization->users()->attach($this->user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'agent',
        ]);
    }

    public function test_first_login_succeeds_normally(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'operador@unitepc.edu.bo',
            'password' => 'Secret123!',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'operador@unitepc.edu.bo');
    }

    public function test_second_login_from_another_device_returns_409_conflict(): void
    {
        config(['session.driver' => 'database']);

        if (! Schema::hasTable('sessions')) {
            $this->markTestSkipped('Sessions table not present.');
        }

        // Simular sesión activa previa de hace 5 minutos en otro equipo
        DB::table('sessions')->insert([
            'id' => 'previous_active_session_id',
            'user_id' => $this->user->getKey(),
            'ip_address' => '190.10.20.30',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            'payload' => base64_encode(serialize(['user_id' => $this->user->getKey()])),
            'last_activity' => Carbon::now()->subMinutes(5)->timestamp,
        ]);

        // Intento de login normal (sin force)
        $response = $this->postJson('/api/login', [
            'email' => 'operador@unitepc.edu.bo',
            'password' => 'Secret123!',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('status', 'session_conflict')
            ->assertJsonPath('active_session.device', 'Google Chrome (Windows)')
            ->assertJsonPath('active_session.ip', '190.10.20.30');
    }

    public function test_login_with_force_true_revokes_previous_session_and_succeeds(): void
    {
        config(['session.driver' => 'database']);

        if (! Schema::hasTable('sessions')) {
            $this->markTestSkipped('Sessions table not present.');
        }

        DB::table('sessions')->insert([
            'id' => 'victim_session_to_be_revoked',
            'user_id' => $this->user->getKey(),
            'ip_address' => '190.10.20.30',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/122.0.0.0 Safari/537.36',
            'payload' => base64_encode(serialize(['user_id' => $this->user->getKey()])),
            'last_activity' => Carbon::now()->subMinutes(2)->timestamp,
        ]);

        // Login con force = true
        $response = $this->postJson('/api/login', [
            'email' => 'operador@unitepc.edu.bo',
            'password' => 'Secret123!',
            'force' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.email', 'operador@unitepc.edu.bo');

        // La sesión anterior debe haber sido eliminada de la tabla sessions
        $this->assertDatabaseMissing('sessions', [
            'id' => 'victim_session_to_be_revoked',
        ]);

        // Debe registrar la marca de revocación en Caché
        $this->assertTrue(Cache::has('revoked_session:victim_session_to_be_revoked'));
    }

    public function test_revoked_session_receives_401_with_session_revoked_another_device_code(): void
    {
        $revokedSessionId = 'revoked_device_session_xyz';
        Cache::put("revoked_session:{$revokedSessionId}", 'another_device', now()->addHour());

        $cookieName = config('session.cookie');

        // Hacer petición unauthenticated con la cookie de la sesión revocada
        $response = $this->withUnencryptedCookie($cookieName, $revokedSessionId)
            ->getJson('/api/me');

        $response->assertStatus(401)
            ->assertJsonPath('code', 'SESSION_REVOKED_ANOTHER_DEVICE');
    }

    public function test_invalid_credentials_fail_with_401(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'operador@unitepc.edu.bo',
            'password' => 'ClaveIncorrecta!',
        ]);

        $response->assertStatus(401);
    }
}
