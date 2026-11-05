<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\HentleAi\Services\AiConversationAnalyzerService;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HentleAiConversationAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $org;
    protected User $user;
    protected Contact $contact;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create([
            'name' => 'UNITEPC AI Test Org',
            'slug' => 'unitepc-ai-analyzer-' . uniqid(),
        ]);

        $this->user = User::factory()->create([
            'current_organization_id' => $this->org->id,
        ]);
        $this->org->users()->attach($this->user->id, [
            'id' => (string) \Illuminate\Support\Str::ulid(),
            'role' => 'admin',
        ]);

        $this->contact = Contact::create([
            'organization_id' => $this->org->id,
            'first_name' => '59178945612',
            'phone' => '+59178945612',
            'status' => 'lead',
        ]);

        $this->conversation = Conversation::create([
            'organization_id' => $this->org->id,
            'contact_id' => $this->contact->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
    }

    public function test_analyzer_detects_disinterest_status_recommendation(): void
    {
        $customStatus = CustomStatus::create([
            'organization_id' => $this->org->id,
            'name' => 'No Interesado',
            'slug' => 'no-interesado',
            'color' => '#64748b',
        ]);

        Message::create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'body' => 'Muchas gracias pero ya no me interesa, ya elegí otra universidad.',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/conversations/{$this->conversation->id}/ai/copilot/analysis");

        $response->assertOk();
        $data = $response->json('data');

        $this->assertNotNull($data['status_recommendation']);
        $this->assertEquals('no_interested', $data['status_recommendation']['suggested_status']);
        $this->assertEquals($customStatus->id, $data['status_recommendation']['suggested_custom_status_id']);
    }

    public function test_apply_status_updates_conversation_and_contact(): void
    {
        $customStatus = CustomStatus::create([
            'organization_id' => $this->org->id,
            'name' => 'Perdido',
            'slug' => 'perdido',
            'color' => '#ef4444',
        ]);

        $response = $this->actingAs($this->user)
            ->postJson("/api/conversations/{$this->conversation->id}/ai/copilot/apply-status", [
                'status' => 'no_interested',
                'custom_status_id' => $customStatus->id,
            ]);

        $response->assertOk();
        $this->assertDatabaseHas('conversations', [
            'id' => $this->conversation->id,
            'status' => 'closed',
            'custom_status_id' => $customStatus->id,
        ]);
        $this->assertDatabaseHas('contacts', [
            'id' => $this->contact->id,
            'custom_status_id' => $customStatus->id,
        ]);
    }

    public function test_analyzer_detects_tags_and_apply_tags_endpoint(): void
    {
        Message::create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'body' => 'Hola, quisiera cotización del arancel de Medicina en la sede Cochabamba.',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/conversations/{$this->conversation->id}/ai/copilot/analysis");

        $response->assertOk();
        $tags = collect($response->json('data.tag_recommendations'))->pluck('name')->all();

        $this->assertContains('Medicina', $tags);
        $this->assertContains('Cotización Solicitada', $tags);
        $this->assertContains('Sede Cochabamba', $tags);

        // Apply tags
        $applyRes = $this->actingAs($this->user)
            ->postJson("/api/conversations/{$this->conversation->id}/ai/copilot/apply-tags", [
                'tags' => ['Medicina', 'Cotización Solicitada'],
            ]);

        $applyRes->assertOk();
        $this->assertDatabaseHas('tags', [
            'organization_id' => $this->org->id,
            'name' => 'Medicina',
        ]);
        $this->assertDatabaseHas('tags', [
            'organization_id' => $this->org->id,
            'name' => 'Cotización Solicitada',
        ]);
    }

    public function test_analyzer_extracts_contact_entities_and_applies_them(): void
    {
        Message::create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'body' => 'Mi nombre es Alejandro Rios, mi email es alejandro@gmail.com y mi CI es 4892110 CB. Vivo en Cochabamba.',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/conversations/{$this->conversation->id}/ai/copilot/analysis");

        $response->assertOk();
        $extracted = $response->json('data.extracted_contact_data');

        $this->assertTrue($extracted['has_new_data']);
        $this->assertEquals('Alejandro Rios', $extracted['first_name']);
        $this->assertEquals('alejandro@gmail.com', $extracted['email']);
        $this->assertEquals('4892110 CB', $extracted['ci']);
        $this->assertEquals('Cochabamba', $extracted['city']);

        // Apply contact data in 1-click
        $applyRes = $this->actingAs($this->user)
            ->postJson("/api/conversations/{$this->conversation->id}/ai/copilot/apply-contact-data", [
                'first_name' => $extracted['first_name'],
                'email' => $extracted['email'],
                'ci' => $extracted['ci'],
                'city' => $extracted['city'],
            ]);

        $applyRes->assertOk();
        $this->contact->refresh();
        $this->assertEquals('Alejandro Rios', $this->contact->first_name);
        $this->assertEquals('alejandro@gmail.com', $this->contact->email);
        $this->assertEquals('4892110 CB', $this->contact->custom_fields['ci']);
        $this->assertEquals('Cochabamba', $this->contact->custom_fields['city']);
    }

    public function test_sentiment_radar_detects_frustration_and_triggers_alert(): void
    {
        Message::create([
            'organization_id' => $this->org->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'body' => 'Pésima atención, llevo horas esperando y nadie responde!! Qué vergüenza de servicio',
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/conversations/{$this->conversation->id}/ai/copilot/analysis");

        $response->assertOk();
        $sentiment = $response->json('data.sentiment');

        $this->assertTrue($sentiment['is_frustrated']);
        $this->assertGreaterThanOrEqual(0.70, $sentiment['frustration_score']);

        // An alert notification should be in app_notifications
        $this->assertDatabaseHas('app_notifications', [
            'organization_id' => $this->org->id,
            'type' => 'frustration_alert',
        ]);
    }
}
