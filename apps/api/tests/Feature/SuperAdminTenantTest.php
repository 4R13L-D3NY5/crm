<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminTenantTest extends TestCase
{
    public function test_superadmin_can_list_tenants_and_summary(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Test',
            'slug' => 'empresa-test-' . uniqid(),
            'plan' => 'pro',
            'monthly_price_usd' => 230.00,
            'expires_at' => now()->addDays(20),
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id, [
            'id' => (string) Str::ulid(),
            'role' => 'owner',
        ]);

        $response = $this->actingAs($user)->getJson('/api/superadmin/tenants');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'summary' => [
                'total_tenants',
                'total_mrr_usd',
                'total_arr_usd',
                'total_phones_leased',
                'active_count',
                'warning_count',
                'expired_count',
                'suspended_count',
            ],
            'tenants',
        ]);
    }

    public function test_superadmin_can_preview_proration_and_renew(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Test 2',
            'slug' => 'empresa-test-2-' . uniqid(),
            'plan' => 'basic',
            'monthly_price_usd' => 120.00,
            'expires_at' => now()->addDays(15),
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id, [
            'id' => (string) Str::ulid(),
            'role' => 'owner',
        ]);

        // 1. Probar previsualización de prorrateo
        $prorationRes = $this->actingAs($user)->postJson("/api/superadmin/tenants/{$org->id}/proration-preview", [
            'new_plan' => 'elite',
            'extra_phones' => 1,
            'extra_agents' => 0,
        ]);
        $prorationRes->assertStatus(200);
        $prorationRes->assertJsonStructure([
            'current_plan',
            'new_plan',
            'prorated_charge_due_today_usd',
        ]);

        // 2. Probar renovación
        $renewRes = $this->actingAs($user)->postJson("/api/superadmin/tenants/{$org->id}/renew", [
            'months' => 1,
            'payment_method' => 'transfer',
            'reference_code' => 'TEST-REF-123',
        ]);
        $renewRes->assertStatus(200);
        $renewRes->assertJsonStructure([
            'success',
            'new_expires_at',
            'amount_charged_usd',
            'license_token',
        ]);
    }

    public function test_superadmin_can_adjust_quotas_with_mid_cycle_proration(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Cuotas Test',
            'slug' => 'empresa-cuotas-' . uniqid(),
            'plan' => 'basic',
            'monthly_price_usd' => 120.00,
            'max_whatsapp_lines' => 1,
            'max_agents' => 3,
            'extra_whatsapp_lines' => 0,
            'extra_agents' => 0,
            'expires_at' => now()->addDays(15),
        ]);

        $user = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);
        $org->users()->attach($user->id, [
            'id' => (string) Str::ulid(),
            'role' => 'owner',
        ]);

        // Ajustar a 2 líneas extra y 1 agente extra a mitad de ciclo
        $res = $this->actingAs($user)->postJson("/api/superadmin/tenants/{$org->id}/adjust-quotas", [
            'extra_phones' => 2,
            'extra_agents' => 1,
            'payment_method' => 'transfer',
            'reference_code' => 'QUOTA-REF-456',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('new_max_whatsapp_lines', 3); // 1 incluido + 2 extra
        $res->assertJsonPath('new_max_agents', 4); // 3 incluidos + 1 extra

        $org->refresh();
        $this->assertEquals(3, $org->max_whatsapp_lines);
        $this->assertEquals(4, $org->max_agents);
        $this->assertEquals(2, $org->extra_whatsapp_lines);
        $this->assertEquals(1, $org->extra_agents);
        $this->assertNotEmpty($org->license_token);

        // Verificar que se registró la factura con prorrateo
        $this->assertDatabaseHas('tenant_invoices', [
            'organization_id' => $org->id,
            'reference_code' => 'QUOTA-REF-456',
        ]);
    }

    public function test_enforces_user_creation_quota_limit(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Límite Usuarios',
            'slug' => 'empresa-lim-users-' . uniqid(),
            'plan' => 'basic',
            'max_agents' => 1, // Límite estricto de 1 usuario
        ]);

        $admin = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);
        $org->users()->attach($admin->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);

        // Intentar crear un segundo usuario superando la cuota permitida
        $res = $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Operador Extra No Permitido',
            'email' => 'operador.bloqueado@ejemplo.com',
            'password' => 'Secreto123*',
            'role' => 'agent',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('code', 'QUOTA_AGENTS_EXCEEDED');
        $this->assertDatabaseMissing('users', ['email' => 'operador.bloqueado@ejemplo.com']);
    }

    public function test_enforces_whatsapp_channel_quota_limit(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Límite Canales',
            'slug' => 'empresa-lim-chan-' . uniqid(),
            'plan' => 'basic',
            'max_whatsapp_lines' => 1, // Límite estricto de 1 canal
        ]);

        $admin = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);
        $org->users()->attach($admin->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);

        // Crear la primera línea permitida
        \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $org->id,
            'name' => 'Canal 1 Permitido',
            'phone_number_id' => 'phone_' . uniqid(),
            'verify_token' => 'token_' . uniqid(),
            'status' => 'CONNECTED',
            'is_active' => true,
        ]);

        // Intentar registrar un segundo canal superando el cupo contratado
        $res = $this->actingAs($admin)->postJson('/api/whatsapp/accounts', [
            'name' => 'Canal 2 Supera Cuota',
            'session_type' => 'baileys_qr',
        ]);

        $res->assertStatus(422);
        $res->assertJsonPath('code', 'QUOTA_CHANNELS_EXCEEDED');
        $this->assertDatabaseMissing('whatsapp_accounts', ['name' => 'Canal 2 Supera Cuota']);
    }

    public function test_enforces_social_media_accounts_quota_limit(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Redes Quota Test',
            'slug' => 'empresa-redes-' . uniqid(),
            'plan' => 'basic',
            'max_whatsapp_lines' => 1,
            'max_social_accounts' => 2,
            'expires_at' => now()->addDays(30),
        ]);

        $admin = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);
        $org->users()->attach($admin->id, [
            'id' => (string) Str::ulid(),
            'role' => 'admin',
        ]);

        // Registrar 2 redes sociales permitidas
        $res1 = $this->actingAs($admin)->postJson('/api/whatsapp/accounts', [
            'name' => 'Fanpage 1',
            'session_type' => 'facebook',
            'phone_number_id' => 'fb_page_1_' . uniqid(),
            'access_token' => 'EAA_valid_token_1',
        ]);
        $res1->assertStatus(201);

        $res2 = $this->actingAs($admin)->postJson('/api/whatsapp/accounts', [
            'name' => 'Instagram 1',
            'session_type' => 'instagram',
            'phone_number_id' => 'ig_act_1_' . uniqid(),
            'access_token' => 'EAA_valid_token_2',
        ]);
        $res2->assertStatus(201);

        // La tercera red debe ser bloqueada por superar max_social_accounts (2)
        $res3 = $this->actingAs($admin)->postJson('/api/whatsapp/accounts', [
            'name' => 'TikTok 1',
            'session_type' => 'tiktok',
            'phone_number_id' => 'tt_act_1_' . uniqid(),
            'access_token' => 'EAA_valid_token_3',
        ]);
        $res3->assertStatus(422);
        $res3->assertJsonPath('code', 'QUOTA_SOCIAL_EXCEEDED');

        // Sin embargo, aún debe permitir registrar el número de WhatsApp permitido
        $resWa = $this->actingAs($admin)->postJson('/api/whatsapp/accounts', [
            'name' => 'WhatsApp Línea 1',
            'session_type' => 'baileys_qr',
        ]);
        $resWa->assertStatus(201);
    }

    public function test_superadmin_can_extend_trial_period(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Trial Test',
            'slug' => 'empresa-trial-' . uniqid(),
            'plan' => 'basic',
            'status' => 'trial',
            'expires_at' => now()->addDays(2),
        ]);

        $admin = User::factory()->create([
            'current_organization_id' => $org->id,
        ]);
        $org->users()->attach($admin->id, [
            'id' => (string) Str::ulid(),
            'role' => 'owner',
        ]);

        $res = $this->actingAs($admin)->postJson("/api/superadmin/tenants/{$org->id}/extend-trial", [
            'days' => 14,
            'plan' => 'pro',
            'extra_phones' => 1,
            'extra_agents' => 2,
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('plan', 'pro');

        $org->refresh();
        $this->assertEquals('trial', $org->status);
        $this->assertEquals('pro', $org->plan);
        $this->assertEquals(1, $org->extra_whatsapp_lines);
        $this->assertEquals(2, $org->extra_agents);
        $this->assertTrue($org->expires_at->gt(now()->addDays(10)));
        $this->assertEquals('23:59:59', $org->expires_at->format('H:i:s'));
    }

    public function test_superadmin_can_set_exact_expiration_date_at_end_of_day(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Exact Date Test',
            'slug' => 'empresa-exact-' . uniqid(),
            'plan' => 'elite',
            'status' => 'active',
            'expires_at' => now()->addDays(5),
        ]);

        $admin = User::factory()->create([
            'current_organization_id' => $org->id,
            'is_superadmin' => true,
        ]);
        $org->users()->attach($admin->id, [
            'id' => (string) Str::ulid(),
            'role' => 'owner',
        ]);

        $targetDate = now()->addDays(20)->format('Y-m-d');

        $res = $this->actingAs($admin)->postJson("/api/superadmin/tenants/{$org->id}/extend-trial", [
            'exact_date' => $targetDate,
            'mode' => 'official',
            'plan' => 'elite',
            'extra_phones' => 4,
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('modalidad', 'official');

        $org->refresh();
        $this->assertEquals('active', $org->status);
        $this->assertEquals($targetDate . ' 23:59:59', $org->expires_at->format('Y-m-d H:i:s'));
        $this->assertEquals(8, $org->max_whatsapp_lines);
        $this->assertEquals(4, $org->extra_whatsapp_lines);
    }

    public function test_superadmin_can_schedule_trial_start_date(): void
    {
        $org = Organization::create([
            'name' => 'Empresa Scheduled Test',
            'slug' => 'empresa-sched-' . uniqid(),
            'plan' => 'pro',
            'status' => 'trial',
        ]);

        $admin = User::factory()->create([
            'current_organization_id' => $org->id,
            'is_superadmin' => true,
        ]);
        $org->users()->attach($admin->id, [
            'id' => (string) Str::ulid(),
            'role' => 'owner',
        ]);

        $startDate = now()->addDays(3)->format('Y-m-d');

        $res = $this->actingAs($admin)->postJson("/api/superadmin/tenants/{$org->id}/extend-trial", [
            'starts_at' => $startDate,
            'days' => 14,
            'mode' => 'trial',
        ]);

        $res->assertStatus(200);
        $res->assertJsonPath('success', true);

        $org->refresh();
        $this->assertEquals($startDate . ' 00:00:00', $org->starts_at->format('Y-m-d H:i:s'));
        $this->assertEquals('23:59:59', $org->expires_at->format('H:i:s'));
    }
}
