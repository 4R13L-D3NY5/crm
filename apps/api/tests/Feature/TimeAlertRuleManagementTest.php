<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Parameters\Models\TimeAlertRule;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimeAlertRuleManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'UNITEPC Time Test Org',
            'slug' => 'unitepc-time-test',
        ]);

        $this->user = User::create([
            'name' => 'Admin Time Test',
            'email' => 'admin-time@unitepc.test',
            'password' => bcrypt('secret123'),
            'current_organization_id' => $this->organization->id,
        ]);

        $this->user->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);
    }

    public function test_auto_seeds_default_rule_with_20min_user_and_120min_client_on_empty_list(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/time-alert-rules');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals(20, $data[0]['user_timeout_minutes']);
        $this->assertEquals(120, $data[0]['client_timeout_minutes']);
        $this->assertTrue($data[0]['apply_to_all_statuses']);
        $this->assertTrue($data[0]['is_active']);
    }

    public function test_can_create_custom_time_alert_rule_with_specific_checkable_statuses(): void
    {
        $this->actingAs($this->user);

        // Crear dos estados
        $status1 = CustomStatus::create([
            'organization_id' => $this->organization->id,
            'name' => 'No Contactado',
            'slug' => 'no-contactado',
            'color' => '#3b82f6',
            'stage_type' => 'initial',
            'is_default' => true,
        ]);

        $status2 = CustomStatus::create([
            'organization_id' => $this->organization->id,
            'name' => 'Inscrito',
            'slug' => 'inscrito',
            'color' => '#10b981',
            'stage_type' => 'won',
        ]);

        $payload = [
            'name' => 'Atención Rápida Postulantes',
            'description' => 'Alerta para leads en etapa inicial sin respuesta',
            'user_timeout_minutes' => 15,
            'client_timeout_minutes' => 60,
            'notify_user_inactivity' => true,
            'notify_client_inactivity' => true,
            'apply_to_all_statuses' => false,
            'custom_status_ids' => [$status1->id],
            'severity' => 'critical',
            'action_type' => 'visual_badge',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/time-alert-rules', $payload);
        $response->assertCreated();

        $ruleId = $response->json('data.id');
        $this->assertDatabaseHas('time_alert_rules', [
            'id' => $ruleId,
            'name' => 'Atención Rápida Postulantes',
            'user_timeout_minutes' => 15,
            'client_timeout_minutes' => 60,
            'severity' => 'critical',
            'apply_to_all_statuses' => false,
        ]);

        // Verificar lectura enriquecida
        $listResponse = $this->getJson('/api/time-alert-rules');
        $listResponse->assertOk();
        $target = collect($listResponse->json('data'))->firstWhere('id', $ruleId);
        $this->assertNotNull($target);
        $this->assertCount(1, $target['assigned_statuses']);
        $this->assertEquals('No Contactado', $target['assigned_statuses'][0]['name']);
    }

    public function test_can_update_time_alert_rule(): void
    {
        $this->actingAs($this->user);

        $rule = TimeAlertRule::create([
            'organization_id' => $this->organization->id,
            'name' => 'Regla Inicial',
            'user_timeout_minutes' => 20,
            'client_timeout_minutes' => 120,
            'apply_to_all_statuses' => true,
        ]);

        $response = $this->putJson("/api/time-alert-rules/{$rule->id}", [
            'name' => 'Regla Modificada',
            'user_timeout_minutes' => 30,
            'client_timeout_minutes' => 180,
            'apply_to_all_statuses' => true,
            'severity' => 'warning',
            'is_active' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('time_alert_rules', [
            'id' => $rule->id,
            'name' => 'Regla Modificada',
            'user_timeout_minutes' => 30,
            'client_timeout_minutes' => 180,
        ]);
    }

    public function test_can_toggle_time_alert_rule_active_state(): void
    {
        $this->actingAs($this->user);

        $rule = TimeAlertRule::create([
            'organization_id' => $this->organization->id,
            'name' => 'Regla Activa',
            'user_timeout_minutes' => 20,
            'client_timeout_minutes' => 120,
            'is_active' => true,
        ]);

        $response = $this->patchJson("/api/time-alert-rules/{$rule->id}/toggle");
        $response->assertOk();

        $this->assertFalse($rule->fresh()->is_active);

        // Toggle back
        $this->patchJson("/api/time-alert-rules/{$rule->id}/toggle");
        $this->assertTrue($rule->fresh()->is_active);
    }

    public function test_can_delete_time_alert_rule(): void
    {
        $this->actingAs($this->user);

        $rule = TimeAlertRule::create([
            'organization_id' => $this->organization->id,
            'name' => 'Regla a borrar',
            'user_timeout_minutes' => 20,
            'client_timeout_minutes' => 120,
        ]);

        $response = $this->deleteJson("/api/time-alert-rules/{$rule->id}");
        $response->assertOk();

        $this->assertDatabaseMissing('time_alert_rules', [
            'id' => $rule->id,
        ]);
    }
}
