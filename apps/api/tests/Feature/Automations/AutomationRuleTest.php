<?php

namespace Tests\Feature\Automations;

use App\Models\User;
use App\Modules\Automations\Models\AutomationRule;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomationRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_list_update_and_delete_automation_rule(): void
    {
        [$user, $organization] = $this->createMembership();

        $createResponse = $this->actingAs($user)->postJson('/api/automation-rules', [
            'name' => 'Auto asignar nuevos WhatsApp',
            'trigger_type' => 'message.inbound.received',
            'conditions' => [
                'channel' => 'whatsapp',
                'message_contains' => 'demo',
            ],
            'actions' => [
                [
                    'type' => 'set_status',
                    'value' => 'pending',
                ],
                [
                    'type' => 'assign_user',
                    'value' => $user->getKey(),
                ],
            ],
            'is_active' => true,
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Auto asignar nuevos WhatsApp');

        $ruleId = $createResponse->json('data.id');

        $this->actingAs($user)->getJson('/api/automation-rules')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->actingAs($user)->putJson("/api/automation-rules/{$ruleId}", [
            'name' => 'Auto asignar inbound',
            'trigger_type' => 'message.inbound.received',
            'conditions' => [
                'channel' => 'whatsapp',
            ],
            'actions' => [
                [
                    'type' => 'set_status',
                    'value' => 'pending',
                ],
            ],
            'is_active' => true,
        ])->assertOk()
            ->assertJsonPath('data.name', 'Auto asignar inbound');

        $this->actingAs($user)->deleteJson("/api/automation-rules/{$ruleId}")
            ->assertOk();

        $this->assertDatabaseMissing('automation_rules', [
            'id' => $ruleId,
        ]);
    }

    public function test_rule_runs_on_manual_conversation_created(): void
    {
        [$user, $organization] = $this->createMembership();

        AutomationRule::factory()->create([
            'organization_id' => $organization->getKey(),
            'trigger_type' => 'conversation.created',
            'conditions' => [
                'channel' => 'manual',
            ],
            'actions' => [
                [
                    'type' => 'set_status',
                    'value' => 'pending',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->postJson('/api/conversations', [
            'subject' => 'Nuevo lead manual',
            'status' => 'open',
            'channel' => 'manual',
            'message' => 'Se registra la conversacion inicial.',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $conversationId = $response->json('data.id');

        $this->assertDatabaseHas('automation_runs', [
            'organization_id' => $organization->getKey(),
            'trigger_type' => 'conversation.created',
        ]);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversationId,
            'status' => 'pending',
        ]);
    }

    public function test_empty_active_filter_lists_all_automation_rules(): void
    {
        [$user, $organization] = $this->createMembership();

        AutomationRule::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Regla activa',
            'is_active' => true,
        ]);

        AutomationRule::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Regla inactiva',
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->getJson('/api/automation-rules?is_active=')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);
    }

    public function test_rule_runs_on_incoming_whatsapp_message_and_assigns_user(): void
    {
        [$user, $organization] = $this->createMembership();
        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone' => '59170001010',
        ]);

        AutomationRule::factory()->create([
            'organization_id' => $organization->getKey(),
            'trigger_type' => 'message.inbound.received',
            'conditions' => [
                'channel' => 'whatsapp',
                'message_contains' => 'demo',
            ],
            'actions' => [
                [
                    'type' => 'assign_user',
                    'value' => $user->getKey(),
                ],
                [
                    'type' => 'set_status',
                    'value' => 'pending',
                ],
            ],
        ]);

        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => '555123456789',
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
                                'name' => $contact->name,
                            ],
                            'wa_id' => $contact->phone,
                        ]],
                        'messages' => [[
                            'from' => $contact->phone,
                            'id' => 'wamid.automation.123',
                            'timestamp' => '1777171717',
                            'type' => 'text',
                            'text' => [
                                'body' => 'Necesito una demo del producto',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

        $this->postJson('/api/whatsapp/webhook', $payload)->assertStatus(202);

        $conversation = Conversation::query()->where('organization_id', $organization->getKey())->first();

        $this->assertNotNull($conversation);
        $this->assertSame('pending', $conversation->status);
        $this->assertDatabaseHas('conversation_assignments', [
            'conversation_id' => $conversation->getKey(),
            'assigned_to_user_id' => $user->getKey(),
        ]);
        $this->assertDatabaseHas('automation_runs', [
            'organization_id' => $organization->getKey(),
            'trigger_type' => 'message.inbound.received',
        ]);
    }

    public function test_rule_can_send_one_whatsapp_auto_reply_per_conversation(): void
    {
        [$user, $organization] = $this->createMembership();
        $autoReply = 'Hola, recibimos tu mensaje. En breve un asesor te respondera.';

        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'messaging_product' => 'whatsapp',
                'contacts' => [
                    ['input' => '59170001010', 'wa_id' => '59170001010'],
                ],
                'messages' => [
                    ['id' => 'wamid.auto.reply.1'],
                ],
            ]),
        ]);

        AutomationRule::factory()->create([
            'organization_id' => $organization->getKey(),
            'trigger_type' => 'message.inbound.received',
            'conditions' => [
                'channel' => 'whatsapp',
            ],
            'actions' => [
                [
                    'type' => 'send_whatsapp_message',
                    'value' => $autoReply,
                ],
            ],
        ]);

        $account = WhatsAppAccount::factory()->create([
            'organization_id' => $organization->getKey(),
            'phone_number_id' => '555123456789',
            'access_token' => 'fake-token',
        ]);

        $this->postJson('/api/whatsapp/webhook', $this->whatsappTextPayload(
            account: $account,
            providerMessageId: 'wamid.inbound.auto.1',
            body: 'Hola, necesito informacion',
        ))->assertStatus(202);

        $this->postJson('/api/whatsapp/webhook', $this->whatsappTextPayload(
            account: $account,
            providerMessageId: 'wamid.inbound.auto.2',
            body: 'Sigo esperando informacion',
        ))->assertStatus(202);

        $conversation = Conversation::query()
            ->where('organization_id', $organization->getKey())
            ->where('channel', 'whatsapp')
            ->first();

        $this->assertNotNull($conversation);
        $this->assertSame(1, Message::query()
            ->where('conversation_id', $conversation->getKey())
            ->where('direction', 'outbound')
            ->where('body', $autoReply)
            ->count());
    }

    private function whatsappTextPayload(
        WhatsAppAccount $account,
        string $providerMessageId,
        string $body,
    ): array {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'id' => $account->business_account_id ?? '123456789',
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
                                'name' => 'Lead WhatsApp',
                            ],
                            'wa_id' => '59170001010',
                        ]],
                        'messages' => [[
                            'from' => '59170001010',
                            'id' => $providerMessageId,
                            'timestamp' => '1777171717',
                            'type' => 'text',
                            'text' => [
                                'body' => $body,
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    private function createMembership(): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'current_organization_id' => $organization->getKey(),
        ]);

        $organization->users()->attach($user->getKey(), [
            'id' => (string) str()->ulid(),
            'role' => 'owner',
        ]);

        return [$user, $organization];
    }
}
