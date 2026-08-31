<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_api_health_endpoint_returns_expected_payload(): void
    {
        $response = $this->getJson('/api/health');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'status' => 'ok',
                    'app' => config('app.name'),
                    'environment' => app()->environment(),
                ],
            ]);
    }
}
