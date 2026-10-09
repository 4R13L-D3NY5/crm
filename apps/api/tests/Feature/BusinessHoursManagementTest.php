<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use App\Modules\Tenancy\Models\Organization;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessHoursManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'UNITEPC Business Hours Org',
            'slug' => 'unitepc-hours-test',
        ]);

        $this->user = User::create([
            'name' => 'Admin Hours Test',
            'email' => 'admin-hours@unitepc.test',
            'password' => bcrypt('secret123'),
            'current_organization_id' => $this->organization->id,
        ]);

        $this->user->organizations()->attach($this->organization->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);
    }

    public function test_auto_initializes_default_business_hours_and_auto_reply(): void
    {
        $this->actingAs($this->user);

        $response = $this->getJson('/api/business-hours');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertEquals('Horario Laboral General', $data['name']);
        $this->assertEquals('immediate', $data['validity_type']);
        $this->assertTrue($data['is_active']);
        $this->assertTrue($data['auto_reply_enabled']);
        $this->assertNotEmpty($data['auto_reply_message']);
        $this->assertArrayHasKey('monday', $data['schedule_days']);
        $this->assertTrue($data['schedule_days']['monday']['enabled']);
        $this->assertFalse($data['schedule_days']['sunday']['enabled']);
    }

    public function test_can_update_schedule_with_immediate_validity_and_custom_days(): void
    {
        $this->actingAs($this->user);

        $days = BusinessHoursConfig::defaultScheduleDays();
        $days['saturday']['enabled'] = true;
        $days['saturday']['start_time'] = '08:00';
        $days['saturday']['end_time'] = '12:00';

        $payload = [
            'name' => 'Horario Turno Completo',
            'validity_type' => 'immediate',
            'timezone' => 'America/La_Paz',
            'schedule_days' => $days,
            'auto_reply_enabled' => true,
            'auto_reply_message' => 'Estamos descansando. Te respondemos a primera hora.',
            'is_active' => true,
        ];

        $response = $this->putJson('/api/business-hours', $payload);
        $response->assertOk();

        $this->assertDatabaseHas('business_hours_configs', [
            'organization_id' => $this->organization->id,
            'name' => 'Horario Turno Completo',
            'validity_type' => 'immediate',
            'auto_reply_enabled' => true,
        ]);

        $data = $response->json('data');
        $this->assertTrue($data['schedule_days']['saturday']['enabled']);
    }

    public function test_can_update_schedule_with_date_range_validity(): void
    {
        $this->actingAs($this->user);

        $payload = [
            'name' => 'Campaña Admisiones Verano',
            'validity_type' => 'date_range',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-31',
            'timezone' => 'America/La_Paz',
            'schedule_days' => BusinessHoursConfig::defaultScheduleDays(),
            'auto_reply_enabled' => false,
            'auto_reply_message' => 'Fuera de horario temporal.',
            'is_active' => true,
        ];

        $response = $this->putJson('/api/business-hours', $payload);
        $response->assertOk();

        $this->assertDatabaseHas('business_hours_configs', [
            'organization_id' => $this->organization->id,
            'validity_type' => 'date_range',
            'start_date' => '2026-11-01',
            'end_date' => '2026-12-31',
            'auto_reply_enabled' => false,
        ]);
    }

    public function test_can_toggle_active_and_auto_reply_states(): void
    {
        $this->actingAs($this->user);

        // Toggle Active
        $res1 = $this->patchJson('/api/business-hours/toggle');
        $res1->assertOk();
        $this->assertFalse($res1->json('data.is_active'));

        $res2 = $this->patchJson('/api/business-hours/toggle');
        $res2->assertOk();
        $this->assertTrue($res2->json('data.is_active'));

        // Toggle Auto Reply
        $res3 = $this->patchJson('/api/business-hours/toggle-auto-reply');
        $res3->assertOk();
        $this->assertFalse($res3->json('data.auto_reply_enabled'));

        $res4 = $this->patchJson('/api/business-hours/toggle-auto-reply');
        $res4->assertOk();
        $this->assertTrue($res4->json('data.auto_reply_enabled'));
    }

    public function test_is_within_hours_calculation_logic(): void
    {
        $config = BusinessHoursConfig::create([
            'organization_id' => $this->organization->id,
            'name' => 'Horario Test',
            'is_active' => true,
            'validity_type' => 'immediate',
            'timezone' => 'America/La_Paz',
            'schedule_days' => BusinessHoursConfig::defaultScheduleDays(), // Lun-Vie 08:30 a 18:30
            'auto_reply_enabled' => true,
            'auto_reply_message' => 'Fuera de horario.',
        ]);

        // Lunes a las 10:00 AM -> Dentro de horario
        $mondayMorning = Carbon::parse('2026-10-12 10:00:00', 'America/La_Paz'); // 2026-10-12 is Monday
        $this->assertTrue($config->isWithinHours($mondayMorning));

        // Lunes a las 20:00 PM -> Fuera de horario (después de 18:30)
        $mondayNight = Carbon::parse('2026-10-12 20:00:00', 'America/La_Paz');
        $this->assertFalse($config->isWithinHours($mondayNight));

        // Domingo a las 11:00 AM -> Fuera de horario (domingo desactivado)
        $sundayMorning = Carbon::parse('2026-10-18 11:00:00', 'America/La_Paz'); // 2026-10-18 is Sunday
        $this->assertFalse($config->isWithinHours($sundayMorning));

        // Con rango de fechas: fecha fuera del rango
        $config->update([
            'validity_type' => 'date_range',
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-30',
        ]);
        $this->assertFalse($config->isWithinHours($mondayMorning)); // 2026-10-12 < 2026-11-01
    }
}
