<?php

namespace Tests\Feature\Reports;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Settings\Models\WorkspaceSetting;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_get_agent_performance_and_sla_report(): void
    {
        $admin = User::factory()->create(['name' => 'Admin Boss']);
        $agent1 = User::factory()->create(['name' => 'Asesor 1', 'email' => 'asesor1@unitepc.edu.bo']);
        $agent2 = User::factory()->create(['name' => 'Asesor 2', 'email' => 'asesor2@unitepc.edu.bo']);

        $org = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $admin->organizations()->attach($org->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $agent1->organizations()->attach($org->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);
        $agent2->organizations()->attach($org->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);

        $admin->update(['current_organization_id' => $org->id]);

        WorkspaceSetting::create([
            'organization_id' => $org->id,
            'sla_timeout_minutes' => 10,
        ]);

        $contact = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Carlos',
            'phone' => '+59170011223',
            'status' => 'active',
        ]);

        // Chat 1: Asignado a Asesor 1, respondido a los 4 minutos (A TIEMPO: 4 min <= 10 min)
        $chat1 = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'closed',
            'assigned_to_user_id' => $agent1->id,
            'rating' => 5,
        ]);
        $chat1->timestamps = false;
        $chat1->forceFill([
            'created_at' => Carbon::now()->subMinutes(30),
            'closed_at' => Carbon::now()->subMinutes(10),
        ])->save();

        $msg1 = Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $chat1->id,
            'user_id' => $agent1->id,
            'direction' => 'outbound',
            'body' => 'Hola Carlos, con gusto te oriento.',
            'sent_at' => Carbon::now()->subMinutes(26),
        ]);
        $msg1->timestamps = false;
        $msg1->forceFill(['created_at' => Carbon::now()->subMinutes(26)])->save();

        // Chat 2: Asignado a Asesor 1, respondido a los 25 minutos (TARDÍO / RETRASO SLA: 25 min > 10 min)
        $chat2 = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'assigned_to_user_id' => $agent1->id,
        ]);
        $chat2->timestamps = false;
        $chat2->forceFill(['created_at' => Carbon::now()->subMinutes(40)])->save();

        $msg2 = Message::create([
            'organization_id' => $org->id,
            'conversation_id' => $chat2->id,
            'user_id' => $agent1->id,
            'direction' => 'outbound',
            'body' => 'Disculpa la demora, aquí tienes los planes.',
            'sent_at' => Carbon::now()->subMinutes(15),
        ]);
        $msg2->timestamps = false;
        $msg2->forceFill(['created_at' => Carbon::now()->subMinutes(15)])->save();

        // Chat 3: Asignado a Asesor 2, SIN RESPONDER (status pending)
        $chat3 = Conversation::create([
            'organization_id' => $org->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'assigned_to_user_id' => $agent2->id,
        ]);
        $chat3->timestamps = false;
        $chat3->forceFill(['created_at' => Carbon::now()->subMinutes(12)])->save();

        $response = $this->actingAs($admin)->getJson('/api/reports/agents');

        $response->assertStatus(200)
            ->assertJsonPath('data.sla_config.timeout_minutes', 10)
            ->assertJsonPath('data.summary.total_assigned', 3)
            ->assertJsonPath('data.summary.total_on_time', 1)
            ->assertJsonPath('data.summary.total_delayed', 1)
            ->assertJsonPath('data.summary.total_unanswered', 1)
            ->assertJsonStructure([
                'data' => [
                    'sla_config' => ['timeout_minutes'],
                    'summary' => [
                        'total_assigned',
                        'total_resolved',
                        'total_on_time',
                        'total_delayed',
                        'total_unanswered',
                        'sla_compliance_rate',
                        'avg_first_response_time',
                        'avg_resolution_time',
                    ],
                    'agents' => [
                        '*' => [
                            'user_id',
                            'name',
                            'email',
                            'assigned_count',
                            'resolved_count',
                            'sla_on_time',
                            'sla_delayed',
                            'unanswered',
                            'sla_compliance_rate',
                            'avg_first_response_time',
                            'avg_resolution_time',
                        ],
                    ],
                ],
            ]);

        $agentsData = collect($response->json('data.agents'));
        $a1 = $agentsData->firstWhere('user_id', $agent1->id);
        $this->assertEquals(2, $a1['assigned_count']);
        $this->assertEquals(1, $a1['sla_on_time']);
        $this->assertEquals(1, $a1['sla_delayed']);
        $this->assertEquals(50.0, $a1['sla_compliance_rate']);
    }

    public function test_admin_can_get_campus_and_degree_categories_report(): void
    {
        $admin = User::factory()->create();
        $org = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $admin->organizations()->attach($org->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $admin->update(['current_organization_id' => $org->id]);

        $wonStatus = CustomStatus::create([
            'organization_id' => $org->id,
            'name' => 'Matriculado',
            'color' => '#10b981',
            'stage_type' => 'won',
        ]);

        $lostStatus = CustomStatus::create([
            'organization_id' => $org->id,
            'name' => 'Desistido',
            'color' => '#ef4444',
            'stage_type' => 'lost',
        ]);

        // Crear Sedes (Raíces)
        $sedeCochabamba = Category::create([
            'organization_id' => $org->id,
            'name' => 'Sede Cochabamba',
            'code' => 'CBB',
            'color' => '#06b6d4',
        ]);

        $sedeLaPaz = Category::create([
            'organization_id' => $org->id,
            'name' => 'Sede La Paz',
            'code' => 'LPZ',
            'color' => '#8b5cf6',
        ]);

        // Crear Carrera dependiente de Cochabamba
        $carreraSistemas = Category::create([
            'organization_id' => $org->id,
            'parent_id' => $sedeCochabamba->id,
            'name' => 'Ingeniería de Sistemas',
            'code' => 'SIS',
            'color' => '#10b981',
        ]);

        // Crear contactos
        $c1 = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Juan',
            'phone' => '+59170010001',
            'status' => 'active',
            'custom_status_id' => $wonStatus->id,
        ]);
        $c1->categories()->attach($carreraSistemas->id, ['id' => (string) Str::ulid(), 'organization_id' => $org->id]);
        $c1->categories()->attach($sedeCochabamba->id, ['id' => (string) Str::ulid(), 'organization_id' => $org->id]);

        $c2 = Contact::create([
            'organization_id' => $org->id,
            'first_name' => 'Maria',
            'phone' => '+59170010002',
            'status' => 'active',
            'custom_status_id' => $lostStatus->id,
        ]);
        $c2->categories()->attach($sedeLaPaz->id, ['id' => (string) Str::ulid(), 'organization_id' => $org->id]);

        $response = $this->actingAs($admin)->getJson('/api/reports/categories');

        $response->assertStatus(200)
            ->assertJsonPath('data.summary.total_categorized_contacts', 2)
            ->assertJsonStructure([
                'data' => [
                    'summary' => [
                        'total_categorized_contacts',
                        'total_uncategorized_contacts',
                        'total_categorized_conversations',
                        'top_campus',
                        'top_career',
                    ],
                    'campuses' => [
                        '*' => [
                            'id',
                            'name',
                            'code',
                            'contacts_count',
                            'won_leads',
                            'lost_leads',
                            'conversion_rate',
                            'subcategories',
                        ],
                    ],
                    'top_careers',
                ],
            ]);
    }

    public function test_admin_can_download_csv_export_reports(): void
    {
        $admin = User::factory()->create();
        $org = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $admin->organizations()->attach($org->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $admin->update(['current_organization_id' => $org->id]);

        // 1. Exportación de agentes en CSV
        $responseAgents = $this->actingAs($admin)->get('/api/reports/export?type=agents&download=1');
        $responseAgents->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $responseAgents->headers->get('content-type'));
        $this->assertStringContainsString('Agente,Correo', (string) $responseAgents->getContent());

        // 2. Exportación de categorías en CSV
        $responseCategories = $this->actingAs($admin)->get('/api/reports/export?type=categories&download=1');
        $responseCategories->assertStatus(200);
        $this->assertStringContainsString('Sede / Categoria Principal', (string) $responseCategories->getContent());
    }
}
