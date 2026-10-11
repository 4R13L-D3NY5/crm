<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Automations\Models\Campaign;
use App\Modules\Automations\Models\CampaignRecipient;
use App\Modules\Automations\Models\ScheduledMessage;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\WhatsApp\Jobs\SendWhatsAppMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase6ScheduledMessagesAndProductivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_query_scheduled_messages_with_stats(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $response = $this->actingAs($user)->postJson('/api/scheduled-messages', [
            'recipient_phone' => '+59178945612',
            'contact_name' => 'Dr. Fernando Rios',
            'body' => 'Confirmación de cita médica para el lunes 10:00 AM',
            'scheduled_at' => Carbon::now()->addDay()->toDateTimeString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.recipient_phone', '+59178945612')
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('scheduled_messages', [
            'organization_id' => $organization->id,
            'recipient_phone' => '+59178945612',
            'status' => 'pending',
        ]);

        $listResponse = $this->actingAs($user)->getJson('/api/scheduled-messages');
        $listResponse->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'stats' => ['total', 'pending', 'sent', 'failed'],
            ])
            ->assertJsonPath('stats.total', 1)
            ->assertJsonPath('stats.pending', 1);
    }

    public function test_send_now_dispatches_scheduled_message_immediately(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Lucia',
            'phone' => '+59176543210',
            'status' => 'active',
        ]);

        $scheduled = ScheduledMessage::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'recipient_phone' => '+59176543210',
            'body' => 'Hola Lucia, tu documento ya está listo para entrega.',
            'scheduled_at' => Carbon::now()->addDays(5),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->postJson("/api/scheduled-messages/{$scheduled->id}/send-now");

        $response->assertStatus(200)
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseHas('scheduled_messages', [
            'id' => $scheduled->id,
            'status' => 'sent',
        ]);

        Queue::assertPushed(SendWhatsAppMessageJob::class);
    }

    public function test_artisan_command_dispatches_due_scheduled_messages(): void
    {
        Queue::fake();

        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);

        $contact = Contact::create([
            'organization_id' => $organization->id,
            'first_name' => 'Roberto',
            'phone' => '+59171234999',
            'status' => 'active',
        ]);

        // Mensaje vencido / listo para enviar
        $dueMessage = ScheduledMessage::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'recipient_phone' => '+59171234999',
            'body' => 'Recordatorio vencido despachado por scheduler.',
            'scheduled_at' => Carbon::now()->subMinutes(5),
            'status' => 'pending',
        ]);

        // Mensaje futuro (no debe despacharse todavía)
        $futureMessage = ScheduledMessage::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'recipient_phone' => '+59171234999',
            'body' => 'Mensaje para el futuro.',
            'scheduled_at' => Carbon::now()->addHours(3),
            'status' => 'pending',
        ]);

        $this->artisan('messages:dispatch-scheduled')->assertExitCode(0);

        $this->assertDatabaseHas('scheduled_messages', [
            'id' => $dueMessage->id,
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('scheduled_messages', [
            'id' => $futureMessage->id,
            'status' => 'pending',
        ]);

        Queue::assertPushed(SendWhatsAppMessageJob::class, 1);
    }

    public function test_artisan_command_dispatches_campaign_recipients(): void
    {
        Queue::fake();

        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);

        $campaign = Campaign::create([
            'organization_id' => $organization->id,
            'name' => 'Campaña Admisiones 2026',
            'status' => 'processing',
            'total_contacts' => 2,
            'sent_count' => 0,
            'failed_count' => 0,
            'delay_seconds' => 10,
            'message_template' => 'Hola {{nombre}}, inicia tu carrera universitaria hoy.',
        ]);

        CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '+59170011223',
            'name' => 'Camila',
            'status' => 'pending',
        ]);

        CampaignRecipient::create([
            'campaign_id' => $campaign->id,
            'phone' => '+59170011224',
            'name' => 'Santiago',
            'status' => 'pending',
        ]);

        $this->artisan('campaigns:dispatch-recipients --limit=10')->assertExitCode(0);

        $this->assertDatabaseHas('campaign_recipients', [
            'campaign_id' => $campaign->id,
            'phone' => '+59170011223',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('campaign_recipients', [
            'campaign_id' => $campaign->id,
            'phone' => '+59170011224',
            'status' => 'sent',
        ]);

        $this->assertDatabaseHas('campaigns', [
            'id' => $campaign->id,
            'status' => 'completed',
            'sent_count' => 2,
        ]);
    }
}
