<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserProfileAndPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'UNITEPC User Profile Org',
            'slug' => 'unitepc-profile-test',
        ]);

        $this->user = User::create([
            'name' => 'Carlos Asesor',
            'email' => 'carlos.asesor@unitepc.test',
            'password' => Hash::make('secret123*'),
            'current_organization_id' => $this->organization->id,
            'presence_status' => 'offline',
        ]);

        $this->user->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'agent',
        ]);
    }

    public function test_user_can_update_profile_info(): void
    {
        $this->actingAs($this->user);

        $response = $this->putJson('/api/me/profile', [
            'name' => 'Carlos Mendoza',
            'email' => 'carlos.mendoza@unitepc.test',
            'phone' => '+591 71234567',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('users', [
            'id' => $this->user->id,
            'name' => 'Carlos Mendoza',
            'email' => 'carlos.mendoza@unitepc.test',
            'phone' => '+591 71234567',
        ]);
    }

    public function test_user_can_update_password(): void
    {
        $this->actingAs($this->user);

        $response = $this->putJson('/api/me/password', [
            'current_password' => 'secret123*',
            'password' => 'newSecret456*',
            'password_confirmation' => 'newSecret456*',
        ]);

        $response->assertOk();
        $this->assertTrue(Hash::check('newSecret456*', $this->user->fresh()->password));
    }

    public function test_user_can_update_presence_status(): void
    {
        $this->actingAs($this->user);

        $response = $this->putJson('/api/me/presence', [
            'presence_status' => 'busy',
            'presence_break_reason' => 'Almuerzo',
        ]);

        $response->assertOk();
        $fresh = $this->user->fresh();
        $this->assertEquals('busy', $fresh->presence_status);
        $this->assertEquals('Almuerzo', $fresh->preferences['presence_break_reason']);
    }

    public function test_user_can_update_preferences(): void
    {
        $this->actingAs($this->user);

        $response = $this->putJson('/api/me/preferences', [
            'theme_mode' => 'light',
            'accent_color' => 'cyan',
            'notification_sound' => 'modern',
            'notification_volume' => 90,
            'desktop_notifications' => true,
            'whatsapp_signature_enabled' => true,
            'whatsapp_signature' => '~ Lic. Carlos Mendoza',
            'language' => 'es',
        ]);

        $response->assertOk();
        $prefs = $this->user->fresh()->preferences_with_defaults;
        $this->assertEquals('light', $prefs['theme_mode']);
        $this->assertEquals('cyan', $prefs['accent_color']);
        $this->assertEquals('modern', $prefs['notification_sound']);
        $this->assertTrue($prefs['whatsapp_signature_enabled']);
        $this->assertEquals('~ Lic. Carlos Mendoza', $prefs['whatsapp_signature']);
    }

    public function test_user_can_setup_and_confirm_two_factor(): void
    {
        $this->actingAs($this->user);

        // 1. Setup
        $setupRes = $this->postJson('/api/me/two-factor/setup');
        $setupRes->assertOk();
        $this->assertNotEmpty($setupRes->json('secret'));
        $this->assertNotEmpty($setupRes->json('qr_code_url'));
        $this->assertCount(8, $setupRes->json('recovery_codes'));

        // 2. Confirm
        $confirmRes = $this->postJson('/api/me/two-factor/confirm', [
            'code' => '123456',
        ]);
        $confirmRes->assertOk();
        $this->assertTrue($this->user->fresh()->hasTwoFactorEnabled());

        // 3. Disable
        $disableRes = $this->postJson('/api/me/two-factor/disable', [
            'password' => 'secret123*',
        ]);
        $disableRes->assertOk();
        $this->assertFalse($this->user->fresh()->hasTwoFactorEnabled());
    }
}
