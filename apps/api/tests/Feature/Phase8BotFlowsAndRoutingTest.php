<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Queues\Models\BotFlow;
use App\Modules\Queues\Models\Queue;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase8BotFlowsAndRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_bot_flow_can_be_created_with_options_and_routing(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $queue = Queue::create([
            'organization_id' => $organization->id,
            'name' => 'Admisiones Pregrado',
            'color' => '#10b981',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/bot-flows', [
            'name' => 'Menú Institucional Principal',
            'greeting_message' => '¡Bienvenido a UNITEPC! Elige una opción para orientarte:',
            'options' => [
                ['option_number' => '1', 'label' => 'Admisiones', 'queue_id' => $queue->id, 'reply_text' => 'Te transferimos con el área de Admisiones.'],
                ['option_number' => '2', 'label' => 'Becas', 'queue_id' => null, 'reply_text' => 'Contamos con Beca Patriota y Deportiva.'],
            ],
            'handoff_to_ai' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Menú Institucional Principal')
            ->assertJsonPath('data.handoff_to_ai', true)
            ->assertJsonCount(2, 'data.options');

        $this->assertDatabaseHas('bot_flows', [
            'organization_id' => $organization->id,
            'name' => 'Menú Institucional Principal',
        ]);
    }

    public function test_bot_flow_engine_routes_to_selected_queue_option(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $admisionesQueue = Queue::create(['organization_id' => $organization->id, 'name' => 'Admisiones', 'color' => '#10b981', 'is_active' => true]);

        $flow = BotFlow::create([
            'organization_id' => $organization->id,
            'name' => 'Menú General',
            'greeting_message' => 'Elige una opción:',
            'options' => [
                ['option_number' => '1', 'label' => 'Admisiones', 'queue_id' => $admisionesQueue->id, 'reply_text' => 'Te derivamos al área de Admisiones.'],
            ],
            'handoff_to_ai' => true,
        ]);

        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Luis', 'phone' => '+59178945612', 'status' => 'active']);
        $conversation = Conversation::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'pending']);

        // El usuario presiona "1"
        $response = $this->actingAs($user)->postJson("/api/bot-flows/{$flow->id}/process-message", [
            'conversation_id' => $conversation->id,
            'message_text' => '1',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'option_matched')
            ->assertJsonPath('data.transferred_to_queue_id', $admisionesQueue->id);

        $this->assertDatabaseHas('conversations', [
            'id' => $conversation->id,
            'queue_id' => $admisionesQueue->id,
        ]);
    }

    public function test_bot_flow_engine_handoff_to_hentle_ai_on_open_question(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $flow = BotFlow::create([
            'organization_id' => $organization->id,
            'name' => 'Menú General',
            'greeting_message' => 'Elige una opción:',
            'options' => [
                ['option_number' => '1', 'label' => 'Admisiones', 'queue_id' => null, 'reply_text' => 'Admisiones'],
            ],
            'handoff_to_ai' => true,
        ]);

        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Carla', 'phone' => '+59178945613', 'status' => 'active']);
        $conversation = Conversation::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'pending']);

        // El usuario escribe una consulta libre ("¿Tienen convenios en Santa Cruz?")
        $response = $this->actingAs($user)->postJson("/api/bot-flows/{$flow->id}/process-message", [
            'conversation_id' => $conversation->id,
            'message_text' => '¿Tienen convenios en Santa Cruz?',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.type', 'ai_handoff');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
        ]);
    }
}
