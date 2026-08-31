<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6ReportsCsatAndSwaggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_analytics_returns_accurate_kpis_and_volume(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Ana', 'phone' => '+59171122334', 'status' => 'active']);

        // Crear 10 tickets
        for ($i = 0; $i < 10; $i++) {
            Conversation::create([
                'organization_id' => $organization->id,
                'contact_id' => $contact->id,
                'channel' => 'whatsapp',
                'status' => $i < 3 ? 'open' : 'pending',
                'rating' => $i < 2 ? 5 : null,
            ]);
        }

        $response = $this->actingAs($user)->getJson('/api/reports/analytics');

        $response->assertStatus(200)
            ->assertJsonPath('data.kpis.chats_created', 10)
            ->assertJsonPath('data.kpis.in_attention', 3)
            ->assertJsonPath('data.kpis.unanswered', 7)
            ->assertJsonStructure([
                'data' => [
                    'kpis' => ['chats_created', 'chats_resolved', 'in_attention', 'unanswered'],
                    'time_metrics' => ['first_response', 'resolution', 'in_chatbot', 'human_attention'],
                    'hourly_evolution',
                ],
            ]);
    }

    public function test_csat_report_calculates_satisfaction_percentage(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'David', 'phone' => '+59174455667', 'status' => 'active']);

        Conversation::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'closed', 'rating' => 5]);
        Conversation::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'closed', 'rating' => 5]);
        Conversation::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'closed', 'rating' => 4]);

        $response = $this->actingAs($user)->getJson('/api/reports/csat');

        $response->assertStatus(200)
            ->assertJsonPath('data.total_responses', 3)
            ->assertJsonStructure([
                'data' => ['average_score', 'total_responses', 'satisfaction_percentage', 'stars_breakdown'],
            ]);
    }

    public function test_openapi_swagger_json_is_public_and_structured(): void
    {
        $response = $this->getJson('/api/docs/openapi.json');

        $response->assertStatus(200)
            ->assertJsonPath('openapi', '3.0.0')
            ->assertJsonPath('info.title', 'Whaticket - API')
            ->assertJsonStructure([
                'openapi',
                'info',
                'servers',
                'components',
                'paths' => ['/contacts', '/whatsapps', '/messages/send'],
            ]);
    }
}
