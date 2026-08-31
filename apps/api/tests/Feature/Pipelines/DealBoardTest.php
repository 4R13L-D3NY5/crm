<?php

namespace Tests\Feature\Pipelines;

use App\Models\User;
use App\Modules\Companies\Models\Company;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Deals\Models\Deal;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DealBoardTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_board_create_deal_and_move_stage(): void
    {
        [$user, $organization] = $this->createMembership();

        $pipeline = Pipeline::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Ventas B2B',
            'is_default' => true,
        ]);

        $newStage = PipelineStage::factory()->create([
            'pipeline_id' => $pipeline->getKey(),
            'name' => 'Nuevo',
            'position' => 1,
            'probability' => 10,
        ]);

        $proposalStage = PipelineStage::factory()->create([
            'pipeline_id' => $pipeline->getKey(),
            'name' => 'Propuesta',
            'position' => 2,
            'probability' => 60,
        ]);

        $contact = Contact::factory()->create([
            'organization_id' => $organization->getKey(),
            'first_name' => 'Ana',
            'last_name' => 'Perez',
        ]);

        $company = Company::factory()->create([
            'organization_id' => $organization->getKey(),
            'name' => 'Demo Corp',
        ]);

        $dealResponse = $this->actingAs($user)->postJson('/api/deals', [
            'pipeline_id' => $pipeline->getKey(),
            'pipeline_stage_id' => $newStage->getKey(),
            'contact_id' => $contact->getKey(),
            'company_id' => $company->getKey(),
            'name' => 'Renovacion anual',
            'status' => 'open',
            'amount' => 1500,
            'probability' => 10,
            'expected_close_date' => '2026-05-15',
            'notes' => 'Deal de prueba',
        ]);

        $dealResponse
            ->assertCreated()
            ->assertJsonPath('data.name', 'Renovacion anual')
            ->assertJsonPath('data.pipeline_stage_id', $newStage->getKey());

        $dealId = $dealResponse->json('data.id');

        $boardResponse = $this->actingAs($user)->getJson("/api/pipelines/{$pipeline->getKey()}/board");

        $boardResponse
            ->assertOk()
            ->assertJsonPath('data.id', $pipeline->getKey())
            ->assertJsonPath('data.stages.0.deals.0.id', $dealId);

        $moveResponse = $this->actingAs($user)->putJson("/api/deals/{$dealId}/stage", [
            'pipeline_stage_id' => $proposalStage->getKey(),
        ]);

        $moveResponse
            ->assertOk()
            ->assertJsonPath('data.pipeline_stage_id', $proposalStage->getKey())
            ->assertJsonPath('data.probability', 60);

        $this->assertDatabaseHas('deal_stage_histories', [
            'deal_id' => $dealId,
            'from_stage_id' => $newStage->getKey(),
            'to_stage_id' => $proposalStage->getKey(),
        ]);
    }

    public function test_board_is_scoped_to_current_organization(): void
    {
        [$user, $organization] = $this->createMembership();
        $otherOrganization = Organization::factory()->create();

        $pipeline = Pipeline::factory()->create([
            'organization_id' => $organization->getKey(),
        ]);

        $foreignPipeline = Pipeline::factory()->create([
            'organization_id' => $otherOrganization->getKey(),
        ]);

        $response = $this->actingAs($user)->getJson("/api/pipelines/{$foreignPipeline->getKey()}/board");

        $response->assertForbidden();

        $visibleResponse = $this->actingAs($user)->getJson("/api/pipelines/{$pipeline->getKey()}/board");

        $visibleResponse->assertOk();
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
