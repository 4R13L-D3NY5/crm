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
    }
}
