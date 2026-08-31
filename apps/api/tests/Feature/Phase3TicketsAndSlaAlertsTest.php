<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Queues\Models\Queue;
use App\Modules\Settings\Models\WorkspaceSetting;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase3TicketsAndSlaAlertsTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_lifecycle_accept_transfer_and_close(): void
    {
        $user = User::factory()->create();
        $agent = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);

        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $agent->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);
        $user->update(['current_organization_id' => $organization->id]);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Mario',
            'phone' => '+59170011223',
            'status' => 'active',
        ]);

        $queueVentas = Queue::create([
            'organization_id' => $organization->id,
            'name' => 'Ventas',
            'color' => '#00a884',
        ]);

        $ticket = Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'queue_id' => $queueVentas->id,
        ]);

        // 1. Aceptar Ticket
        $acceptResponse = $this->actingAs($user)->postJson("/api/conversations/{$ticket->id}/accept");
        $acceptResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.assigned_to_user_id', $user->id);

        // 2. Transferir Ticket
        $transferResponse = $this->actingAs($user)->postJson("/api/conversations/{$ticket->id}/transfer", [
            'user_id' => $agent->id,
            'note' => 'Paso a soporte técnico especializado',
        ]);
        $transferResponse->assertStatus(200)
            ->assertJsonPath('data.assigned_to_user_id', $agent->id);

        // Verificar que se creó la nota interna automática
        $this->assertDatabaseHas('messages', [
            'conversation_id' => $ticket->id,
            'is_internal' => true,
        ]);

        // 3. Finalizar Ticket
        $closeResponse = $this->actingAs($user)->postJson("/api/conversations/{$ticket->id}/close", [
            'rating' => 5,
            'feedback' => 'Excelente atención rápida',
        ]);
        $closeResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'closed')
            ->assertJsonPath('data.rating', 5);
    }

    public function test_supervisor_ghost_mode_messaging(): void
    {
        $supervisor = User::factory()->create();
        $agent = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);

        $supervisor->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $agent->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'agent']);
        $supervisor->update(['current_organization_id' => $organization->id]);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Carla',
            'phone' => '+59179988776',
            'status' => 'active',
        ]);

        $ticket = Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'open',
            'assigned_to_user_id' => $agent->id, // Asignado al agente
        ]);

        // Supervisor responde sin desasignar
        $response = $this->actingAs($supervisor)->postJson("/api/conversations/{$ticket->id}/messages/supervisor", [
            'body' => 'Hola, te habla el supervisor de admisiones.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('assigned_to_user_id', $agent->id); // Sigue asignado al agente

        $ticket->refresh();
        $this->assertEquals($agent->id, $ticket->assigned_to_user_id);
    }

    public function test_sla_unanswered_tickets_alerts(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        WorkspaceSetting::create([
            'organization_id' => $organization->id,
            'sla_timeout_minutes' => 10,
        ]);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Pedro',
            'phone' => '+59173344556',
            'status' => 'active',
        ]);

        // Ticket sin responder desde hace 15 minutos (SLA vencido)
        $ticket = Conversation::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'channel' => 'whatsapp',
            'status' => 'pending',
            'last_message_at' => Carbon::now()->subMinutes(15),
        ]);

        $response = $this->actingAs($user)->getJson('/api/conversations/sla-alerts');

        $response->assertStatus(200)
            ->assertJsonPath('total_alerts', 1)
            ->assertJsonPath('data.0.sla_exceeded', true);
    }
}
