<?php

namespace Tests\Feature\Hardening;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_endpoint_is_rate_limited(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'password',
        ]);

        $organization->users()->attach($user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'owner',
        ]);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/login', [
                'email' => 'owner@example.com',
                'password' => 'invalid-password',
            ])->assertStatus(401);
        }

        $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'invalid-password',
        ])->assertStatus(429);
    }
}
