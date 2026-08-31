<?php

namespace Tests\Feature\Hardening;

use App\Models\User;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Shared\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_organization_switch_are_audited(): void
    {
        $primaryOrganization = Organization::factory()->create();
        $secondaryOrganization = Organization::factory()->create();

        $user = User::factory()->create([
            'email' => 'owner@example.com',
            'password' => 'password',
            'current_organization_id' => $primaryOrganization->getKey(),
        ]);

        $primaryOrganization->users()->attach($user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'owner',
        ]);

        $secondaryOrganization->users()->attach($user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'owner',
        ]);

        $this->postJson('/api/login', [
            'email' => 'owner@example.com',
            'password' => 'password',
        ])->assertOk();

        $this->actingAs($user)->putJson('/api/organizations/current', [
            'organization_id' => $secondaryOrganization->getKey(),
        ])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->getKey(),
            'event' => 'auth.login',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->getKey(),
            'organization_id' => $secondaryOrganization->getKey(),
            'event' => 'tenancy.organization_switched',
        ]);
    }

    public function test_whatsapp_webhook_is_audited(): void
    {
        $organization = Organization::factory()->create();
        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => '555123456789',
            'display_phone_number' => '+59170000001',
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => '123456789',
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'messaging_product' => 'whatsapp',
                        'metadata' => [
                            'display_phone_number' => '+59170000001',
                            'phone_number_id' => $account->phone_number_id,
                        ],
                        'contacts' => [[
                            'profile' => [
                                'name' => 'Carlos Demo',
                            ],
                            'wa_id' => '59171122334',
                        ]],
                        'messages' => [[
                            'from' => '59171122334',
                            'id' => 'wamid.HBgLMzkxNzAwMDAwMTERAgARGBI1',
                            'timestamp' => '1777171717',
                            'type' => 'text',
                            'text' => [
                                'body' => 'Hola, necesito informacion del servicio.',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)->assertStatus(202);

        $audit = AuditLog::query()
            ->where('event', 'whatsapp.webhook_received')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($organization->getKey(), $audit->organization_id);
        $this->assertSame('555123456789', $audit->metadata['phone_number_id']);
    }
}
