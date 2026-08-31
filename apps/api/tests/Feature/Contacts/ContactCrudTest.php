<?php

namespace Tests\Feature\Contacts;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Deals\Models\Deal;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_list_show_update_and_delete_contact(): void
    {
        [$user, $organization] = $this->createMembership();

        $createResponse = $this->actingAs($user)->postJson('/api/contacts', [
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'email' => 'ana@example.com',
            'phone' => '+59170000001',
            'status' => 'lead',
            'notes' => 'Lead prioritario',
            'tags' => ['vip', 'whatsapp'],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ana Lopez')
            ->assertJsonCount(2, 'data.tags');

        $contactId = $createResponse->json('data.id');

        $this->assertDatabaseHas('contacts', [
            'id' => $contactId,
            'organization_id' => $organization->getKey(),
            'status' => 'lead',
        ]);

        $listResponse = $this->actingAs($user)->getJson('/api/contacts?search=Ana&status=lead');
        $listResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $showResponse = $this->actingAs($user)->getJson("/api/contacts/{$contactId}");
        $showResponse
            ->assertOk()
            ->assertJsonPath('data.email', 'ana@example.com');

        $updateResponse = $this->actingAs($user)->putJson("/api/contacts/{$contactId}", [
            'first_name' => 'Ana Maria',
            'last_name' => 'Lopez',
            'email' => 'ana@example.com',
            'phone' => '+59170000002',
            'status' => 'active',
            'notes' => 'Cliente activo',
            'tags' => ['vip'],
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.name', 'Ana Maria Lopez')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(1, 'data.tags');

        $deleteResponse = $this->actingAs($user)->deleteJson("/api/contacts/{$contactId}");
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('contacts', [
            'id' => $contactId,
        ]);
    }

    public function test_contact_list_can_filter_by_tag_and_is_scoped_to_current_organization(): void
    {
        [$user, $organization] = $this->createMembership();
        $otherOrganization = Organization::factory()->create();

        $vipTag = Tag::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'VIP',
            'slug' => 'vip',
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Marco',
            'status' => 'active',
        ]);
        $contact->tags()->attach($vipTag->getKey());

        Contact::factory()->create([
            'organization_id' => $otherOrganization->getKey(),
            'first_name' => 'Oculto',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->getJson('/api/contacts?tag=vip');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Marco '.$contact->last_name);
    }

    public function test_contact_detail_includes_related_company_deals_and_conversations(): void
    {
        [$user, $organization] = $this->createMembership();

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Paola',
            'last_name' => 'Rivera',
        ]);

        $company = Company::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Grupo Altamar',
        ]);
        $company->contacts()->attach($contact->getKey());

        $pipeline = Pipeline::factory()->create([
            'organization_id' => $organization->getKey(),
        ]);
        $stage = PipelineStage::factory()->create([
            'pipeline_id' => $pipeline->getKey(),
        ]);

        Deal::factory()->create([
            'organization_id' => $organization->getKey(),
            'pipeline_id' => $pipeline->getKey(),
            'pipeline_stage_id' => $stage->getKey(),
            'contact_id' => $contact->getKey(),
            'company_id' => $company->getKey(),
            'name' => 'Expansion omnicanal',
        ]);

        Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'contact_id' => $contact->getKey(),
            'company_id' => $company->getKey(),
            'subject' => 'Seguimiento inicial',
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->getJson("/api/contacts/{$contact->getKey()}");

        $response
            ->assertOk()
            ->assertJsonPath('data.companies.0.name', 'Grupo Altamar')
            ->assertJsonPath('data.deals.0.name', 'Expansion omnicanal')
            ->assertJsonPath('data.conversations.0.subject', 'Seguimiento inicial');
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
