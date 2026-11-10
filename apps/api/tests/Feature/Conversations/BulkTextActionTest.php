<?php

namespace Tests\Feature\Conversations;

use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Parameters\Models\Category;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkTextActionTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_can_preview_conversations_and_contacts_by_message_text_and_direction(): void
    {
        [$user, $organization] = $this->createMembership();

        $contact1 = Contact::factory()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Carlos',
            'last_name' => 'Valdez',
            'phone' => '+59170000001',
        ]);
        $conv1 = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'contact_id' => $contact1->id,
            'channel' => 'whatsapp',
        ]);
        // Mensaje entrante (inbound) del cliente preguntando por beca anticipada
        Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conv1->id,
            'direction' => 'inbound',
            'body' => 'Hola, me gustaría saber requisitos para la beca anticipada por favor',
        ]);

        $contact2 = Contact::factory()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Maria',
            'last_name' => 'Perez',
            'phone' => '+59170000002',
        ]);
        $conv2 = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'contact_id' => $contact2->id,
            'channel' => 'whatsapp',
        ]);
        // Mensaje saliente (outbound) de bot/asesor ofreciendo beca anticipada
        Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conv2->id,
            'direction' => 'outbound',
            'body' => 'Aprovecha nuestra promoción de Beca Anticipada con 40% de descuento.',
        ]);

        $contact3 = Contact::factory()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Juan',
            'last_name' => 'Lopez',
            'phone' => '+59170000003',
        ]);
        $conv3 = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'contact_id' => $contact3->id,
            'channel' => 'whatsapp',
        ]);
        // Mensaje sin el término
        Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conv3->id,
            'direction' => 'inbound',
            'body' => 'Buenas tardes, horarios de atención?',
        ]);

        // 1. Preview con direction = both -> debe encontrar 2
        $resBoth = $this->actingAs($user)->postJson('/api/conversations/bulk-text-preview', [
            'text' => 'beca anticipada',
            'direction' => 'both',
        ]);
        $resBoth->assertOk()
            ->assertJsonPath('data.conversations_count', 2)
            ->assertJsonPath('data.contacts_count', 2);

        // 2. Preview con direction = inbound -> debe encontrar 1 (contact1)
        $resInbound = $this->actingAs($user)->postJson('/api/conversations/bulk-text-preview', [
            'text' => 'beca anticipada',
            'direction' => 'inbound',
        ]);
        $resInbound->assertOk()
            ->assertJsonPath('data.conversations_count', 1)
            ->assertJsonPath('data.contacts_count', 1)
            ->assertJsonPath('data.sample.0.contact_phone', '+59170000001');

        // 3. Preview con direction = outbound -> debe encontrar 1 (contact2)
        $resOutbound = $this->actingAs($user)->postJson('/api/conversations/bulk-text-preview', [
            'text' => 'beca anticipada',
            'direction' => 'outbound',
        ]);
        $resOutbound->assertOk()
            ->assertJsonPath('data.conversations_count', 1)
            ->assertJsonPath('data.contacts_count', 1)
            ->assertJsonPath('data.sample.0.contact_phone', '+59170000002');
    }

    public function test_can_classify_and_assign_tags_categories_and_status_in_bulk(): void
    {
        [$user, $organization] = $this->createMembership();

        $contact = Contact::factory()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Carla',
            'last_name' => 'Rios',
            'phone' => '+59178888888',
        ]);
        $waAccount = \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Línea Central WhatsApp',
            'display_phone_number' => '+59172100010',
            'phone_number_id' => '1234567890',
            'business_account_id' => 'waba_123',
            'verify_token' => 'verify_token_123',
            'access_token' => 'token_test',
        ]);

        $conv = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $waAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);
        Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conv->id,
            'direction' => 'outbound',
            'body' => 'Información de beca anticipada enviada.',
        ]);

        $category = Category::create([
            'organization_id' => $organization->id,
            'name' => 'Promociones Especiales',
            'slug' => 'promociones-especiales',
        ]);

        $classifyRes = $this->actingAs($user)->postJson('/api/conversations/bulk-text-classify', [
            'text' => 'beca anticipada',
            'direction' => 'both',
            'new_tag_name' => 'Beca 2027',
            'category_ids' => [$category->id],
            'status' => 'pending',
        ]);

        $classifyRes->assertOk()
            ->assertJsonPath('data.affected_conversations', 1)
            ->assertJsonPath('data.affected_contacts', 1);

        // Verificar que la etiqueta se creó y vinculó al contacto
        $tag = Tag::where('organization_id', $organization->id)->where('name', 'Beca 2027')->first();
        $this->assertNotNull($tag);
        $this->assertTrue($contact->tags()->where('tags.id', $tag->id)->exists());

        // Verificar que la categoría se vinculó a la conversación
        $this->assertTrue($conv->categories()->where('categories.id', $category->id)->exists());

        // Verificar que el estado del ticket cambió a pending
        $this->assertEquals('pending', $conv->fresh()->status);
    }

    public function test_can_export_csv_report_with_crm_direct_links_and_whatsapp_details(): void
    {
        [$user, $organization] = $this->createMembership();

        $contact = Contact::factory()->create([
            'organization_id' => $organization->id,
            'first_name' => 'Roberto',
            'last_name' => 'Torrez',
            'phone' => '+59173333333',
        ]);
        $waAccount = \App\Modules\WhatsApp\Models\WhatsAppAccount::create([
            'organization_id' => $organization->id,
            'name' => 'Línea Central WhatsApp',
            'display_phone_number' => '+59172100010',
            'phone_number_id' => '1234567890',
            'business_account_id' => 'waba_123',
            'verify_token' => 'verify_token_123',
            'access_token' => 'token_test',
        ]);

        $conv = Conversation::factory()->create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $waAccount->id,
            'channel' => 'whatsapp',
            'status' => 'open',
        ]);
        Message::factory()->create([
            'organization_id' => $organization->id,
            'conversation_id' => $conv->id,
            'direction' => 'inbound',
            'body' => 'Quiero información sobre beca anticipada',
        ]);

        // 1. Export XLSX nativo por defecto
        $responseXlsx = $this->actingAs($user)->get('/api/conversations/bulk-text-export?text=beca%20anticipada&direction=both');
        $responseXlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml.sheet', (string) $responseXlsx->headers->get('content-type'));
        $this->assertStringContainsString('.xlsx', (string) $responseXlsx->headers->get('content-disposition'));
        $xlsxContent = $responseXlsx->getContent();
        $this->assertNotEmpty($xlsxContent);
        // Archivos XLSX son paquetes ZIP, inician con firma PK (0x50 0x4B)
        $this->assertSame("PK", substr($xlsxContent, 0, 2));

        // 2. Export CSV con punto y coma y directiva sep=;
        $responseCsv = $this->actingAs($user)->get('/api/conversations/bulk-text-export?text=beca%20anticipada&direction=both&format=csv');
        $responseCsv->assertOk();
        $this->assertStringContainsString('text/csv', (string) $responseCsv->headers->get('content-type'));
        $this->assertStringContainsString('.csv', (string) $responseCsv->headers->get('content-disposition'));

        $csvContent = $responseCsv->getContent();
        $this->assertStringContainsString('sep=;', $csvContent);
        $this->assertStringContainsString('Enlace CRM (Abrir Chat)', $csvContent);
        $this->assertStringContainsString('Teléfono / WhatsApp', $csvContent);
        $this->assertStringContainsString('WHATSAPP', $csvContent);
        $this->assertStringContainsString('+59173333333', $csvContent);
        $this->assertStringContainsString("app/conversations?id={$conv->id}", $csvContent);
        $this->assertStringContainsString('Roberto Torrez', $csvContent);
        $this->assertStringContainsString('Quiero información sobre beca anticipada', $csvContent);
    }
}
