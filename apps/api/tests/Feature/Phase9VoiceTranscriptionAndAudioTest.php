<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Events\TicketMessageCreatedEvent;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase9VoiceTranscriptionAndAudioTest extends TestCase
{
    use RefreshDatabase;

    public function test_voice_note_message_can_be_transcribed_to_text(): void
    {
        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Mario', 'phone' => '+59171234567', 'status' => 'active']);
        $conversation = Conversation::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);

        $message = Message::create([
            'organization_id' => $organization->id,
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'message_type' => 'audio',
            'media_type' => 'audio',
            'media_url' => '/storage/audio/incoming_note.ogg',
            'media_duration_seconds' => 14,
            'body' => '[Nota de voz]',
            'transcription_status' => 'none',
        ]);

        $response = $this->actingAs($user)->postJson("/api/conversations/{$conversation->id}/messages/{$message->id}/transcribe");

        $response->assertStatus(200)
            ->assertJsonPath('data.transcription_status', 'completed')
            ->assertJsonPath('data.media_duration_seconds', 14);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'transcription_status' => 'completed',
        ]);
    }

    public function test_voice_note_synthesis_creates_outbound_audio_message(): void
    {
        Event::fake([TicketMessageCreatedEvent::class]);

        $user = User::factory()->create();
        $organization = Organization::create(['name' => 'UNITEPC', 'slug' => 'unitepc']);
        $user->organizations()->attach($organization->id, ['id' => (string) Str::ulid(), 'role' => 'admin']);
        $user->update(['current_organization_id' => $organization->id]);

        $contact = Contact::create(['organization_id' => $organization->id, 'first_name' => 'Elena', 'phone' => '+59179876543', 'status' => 'active']);
        $conversation = Conversation::create(['organization_id' => $organization->id, 'contact_id' => $contact->id, 'channel' => 'whatsapp', 'status' => 'open']);

        $response = $this->actingAs($user)->postJson("/api/conversations/{$conversation->id}/voice-notes/synthesize", [
            'text' => 'Hola Elena, con gusto te enviamos la malla curricular de Odontología 2026.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.direction', 'outbound')
            ->assertJsonPath('data.message_type', 'audio')
            ->assertJsonPath('data.transcription_status', 'completed')
            ->assertJsonPath('data.body', 'Hola Elena, con gusto te enviamos la malla curricular de Odontología 2026.');

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'message_type' => 'audio',
        ]);

        Event::assertDispatched(TicketMessageCreatedEvent::class);
    }
}
