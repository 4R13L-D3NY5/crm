<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Deals\Models\Deal;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase10DealPipelineAndKanbanTest extends TestCase
{
    use RefreshDatabase;

    public function test_pipeline_board_returns_stages_and_deals(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $pipeline = Pipeline::create([
            'organization_id' => $organization->id,
            'name' => 'Admisiones Pregrado 2026',
            'is_default' => true,
        ]);

        $stage1 = PipelineStage::create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Nuevo Prospecto',
            'position' => 1,
            'color' => '#6366f1',
        ]);

        $stage2 = PipelineStage::create([
            'pipeline_id' => $pipeline->id,
            'name' => 'Matriculado',
            'position' => 2,
            'color' => '#10b981',
        ]);

        $deal = Deal::create([
            'organization_id' => $organization->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage1->id,
            'name' => 'Postulación Odontología - Sofia',
            'amount' => 4500.00,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->getJson("/api/pipelines/{$pipeline->id}/board");

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Admisiones Pregrado 2026')
            ->assertJsonCount(2, 'data.stages');
    }

    public function test_deal_can_be_moved_between_stages(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $pipeline = Pipeline::create(['organization_id' => $organization->id, 'name' => 'Ventas', 'is_default' => true]);
        $stage1 = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Etapa 1', 'position' => 1, 'color' => '#6366f1']);
        $stage2 = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Etapa 2', 'position' => 2, 'color' => '#10b981']);

        $deal = Deal::create([
            'organization_id' => $organization->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage1->id,
            'name' => 'Oportunidad Demo',
            'amount' => 1200.00,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->putJson("/api/deals/{$deal->id}/stage", [
            'pipeline_stage_id' => $stage2->id,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.pipeline_stage_id', $stage2->id);

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'pipeline_stage_id' => $stage2->id,
        ]);
    }

    public function test_deal_can_be_linked_to_whatsapp_conversation(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $pipeline = Pipeline::create(['organization_id' => $organization->id, 'name' => 'Admisiones', 'is_default' => true]);
        $stage = PipelineStage::create(['pipeline_id' => $pipeline->id, 'name' => 'Prospecto', 'position' => 1, 'color' => '#6366f1']);

        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Raul', 'phone' => '+59178945612', 'status' => 'active']);
        $conversation = Conversation::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);

        $deal = Deal::create([
            'organization_id' => $organization->id,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $stage->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'name' => 'Matrícula Medicina - Raul',
            'amount' => 5800.00,
            'status' => 'open',
        ]);

        $response = $this->actingAs($user)->getJson("/api/deals/{$deal->id}");

        $response->assertStatus(200)
            ->assertJsonPath('data.conversation_id', $conversation->id)
            ->assertJsonPath('data.amount', 5800);

        $this->assertDatabaseHas('deals', [
            'id' => $deal->id,
            'conversation_id' => $conversation->id,
        ]);
    }
}
