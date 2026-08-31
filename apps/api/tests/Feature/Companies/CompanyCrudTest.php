<?php

namespace Tests\Feature\Companies;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Deals\Models\Deal;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompanyCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_list_show_update_and_delete_company(): void
    {
        [$user, $organization] = $this->createMembership();
        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Lucia',
            'last_name' => 'Perez',
        ]);

        $createResponse = $this->actingAs($user)->postJson('/api/companies', [
            'name' => 'Nova Labs',
            'industry' => 'Tecnologia',
            'website' => 'https://nova.test',
            'email' => 'contacto@nova.test',
            'phone' => '+59170000010',
            'status' => 'lead',
            'notes' => 'Cuenta prioritaria',
            'contact_ids' => [$contact->getKey()],
        ]);

        $createResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Nova Labs')
            ->assertJsonCount(1, 'data.contacts');

        $companyId = $createResponse->json('data.id');

        $listResponse = $this->actingAs($user)->getJson('/api/companies?search=Nova&status=lead');
        $listResponse
            ->assertOk()
            ->assertJsonPath('meta.total', 1);

        $showResponse = $this->actingAs($user)->getJson("/api/companies/{$companyId}");
        $showResponse
            ->assertOk()
            ->assertJsonPath('data.email', 'contacto@nova.test');

        $updateResponse = $this->actingAs($user)->putJson("/api/companies/{$companyId}", [
            'name' => 'Nova Labs SRL',
            'industry' => 'Servicios',
            'website' => 'https://nova.test',
            'email' => 'hola@nova.test',
            'phone' => '+59170000011',
            'status' => 'active',
            'notes' => 'Cliente activo',
            'contact_ids' => [],
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('data.name', 'Nova Labs SRL')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(0, 'data.contacts');

        $deleteResponse = $this->actingAs($user)->deleteJson("/api/companies/{$companyId}");
        $deleteResponse->assertOk();

        $this->assertSoftDeleted('companies', [
            'id' => $companyId,
        ]);
    }

    public function test_company_list_is_scoped_to_current_organization(): void
    {
        [$user, $organization] = $this->createMembership();
        $otherOrganization = Organization::factory()->create();

        Company::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Visible Corp',
        ]);

        Company::factory()->create([
            'organization_id' => $otherOrganization->getKey(),
            'name' => 'Oculta Corp',
        ]);

        $response = $this->actingAs($user)->getJson('/api/companies');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'Visible Corp');
    }

    public function test_company_detail_includes_contacts_deals_and_conversations(): void
    {
        [$user, $organization] = $this->createMembership();

        $company = Company::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Grupo Altamar',
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Paola',
            'last_name' => 'Rivera',
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
            'company_id' => $company->getKey(),
            'contact_id' => $contact->getKey(),
            'name' => 'Expansion omnicanal',
        ]);

        Conversation::factory()->create([
            'organization_id' => $organization->getKey(),
            'company_id' => $company->getKey(),
            'contact_id' => $contact->getKey(),
            'subject' => 'Seguimiento corporativo',
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->getJson("/api/companies/{$company->getKey()}");

        $response
            ->assertOk()
            ->assertJsonPath('data.contacts.0.name', 'Paola Rivera')
            ->assertJsonPath('data.deals.0.name', 'Expansion omnicanal')
            ->assertJsonPath('data.conversations.0.subject', 'Seguimiento corporativo');
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
