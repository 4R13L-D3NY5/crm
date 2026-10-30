<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriptionPeriodNotificationTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Tech Solutions Corp',
            'slug' => 'tech-solutions',
            'plan' => 'pro',
            'status' => 'trial',
            'expires_at' => Carbon::now()->addDays(3),
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->organization->id,
        ]);

        $this->user->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);
    }

    public function test_auth_me_returns_organization_subscription_and_expiration_metadata(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/me');

        $response->assertStatus(200)
            ->assertJsonPath('data.current_organization.plan', 'pro')
            ->assertJsonPath('data.current_organization.status', 'trial')
            ->assertJsonPath('data.current_organization.is_trial', true)
            ->assertJsonPath('data.current_organization.is_expired', false)
            ->assertJsonPath('data.current_organization.days_remaining', 3);
    }

    public function test_notifications_include_billing_alert_when_trial_is_expiring_soon(): void
    {
        $service = app(NotificationService::class);
        $result = $service->getForUser($this->user);

        $this->assertGreaterThanOrEqual(1, $result['unread_count']);

        $notifications = collect($result['notifications']);
        $billingAlert = $notifications->firstWhere('type', 'billing');

        $this->assertNotNull($billingAlert);
        $this->assertStringContainsString('prueba gratuita', $billingAlert['title']);
        $this->assertStringContainsString('3 días', $billingAlert['title']);
        $this->assertSame('/app/pricing', $billingAlert['action_url']);
        $this->assertSame(3, $billingAlert['data']['days_remaining']);
        $this->assertTrue($billingAlert['data']['is_trial']);
        $this->assertFalse($billingAlert['data']['is_expired']);
    }

    public function test_notifications_include_expired_alert_when_period_has_ended(): void
    {
        $this->organization->update([
            'status' => 'active',
            'expires_at' => Carbon::now()->subDays(2),
        ]);
        $this->user->refresh();

        $service = app(NotificationService::class);
        $result = $service->getForUser($this->user);

        $notifications = collect($result['notifications']);
        $billingAlert = $notifications->firstWhere('type', 'billing');

        $this->assertNotNull($billingAlert);
        $this->assertStringContainsString('concluido', $billingAlert['title']);
        $this->assertTrue($billingAlert['data']['is_expired']);
        $this->assertLessThan(0, $billingAlert['data']['days_remaining']);
    }

    public function test_no_billing_alert_generated_when_period_has_ample_time(): void
    {
        $this->organization->update([
            'status' => 'active',
            'expires_at' => Carbon::now()->addDays(25),
        ]);
        $this->user->refresh();

        $service = app(NotificationService::class);
        $result = $service->getForUser($this->user);

        $notifications = collect($result['notifications']);
        $billingAlert = $notifications->firstWhere('type', 'billing');

        $this->assertNull($billingAlert);
    }

    public function test_marking_virtual_billing_alert_as_read_succeeds(): void
    {
        $service = app(NotificationService::class);
        $success = $service->markAsRead("billing_exp_{$this->organization->id}", $this->user);

        $this->assertTrue($success);
    }
}
