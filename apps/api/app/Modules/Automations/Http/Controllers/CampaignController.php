<?php

namespace App\Modules\Automations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automations\Models\Campaign;
use App\Modules\Automations\Models\CampaignRecipient;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CampaignController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $campaigns = Campaign::where('organization_id', $organization->id)
            ->when($request->filled('status') && $request->input('status') !== 'all', function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            })
            ->with(['whatsappAccount'])
            ->latest()
            ->get();

        return response()->json(['data' => $campaigns]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'whatsapp_account_id' => ['nullable', 'string', 'exists:whatsapp_accounts,id'],
            'message_template' => ['required', 'string', 'max:4000'],
            'delay_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'tag_ids' => ['nullable', 'array'],
        ]);

        // Obtener contactos según tags o todos
        $contactsQuery = Contact::where('organization_id', $organization->id)->whereNotNull('phone');
        if (!empty($validated['tag_ids'])) {
            $contactsQuery->whereHas('tags', function ($q) use ($validated) {
                $q->whereIn('tags.id', $validated['tag_ids']);
            });
        }
        $contacts = $contactsQuery->get();

        $campaign = Campaign::create([
            'organization_id' => $organization->id,
            'whatsapp_account_id' => $validated['whatsapp_account_id'] ?? null,
            'name' => $validated['name'],
            'status' => 'draft',
            'total_contacts' => $contacts->count(),
            'sent_count' => 0,
            'failed_count' => 0,
            'delay_seconds' => $validated['delay_seconds'] ?? 20,
            'message_template' => $validated['message_template'],
        ]);

        foreach ($contacts as $c) {
            CampaignRecipient::create([
                'campaign_id' => $campaign->id,
                'contact_id' => $c->id,
                'phone' => $c->phone,
                'name' => $c->name,
                'status' => 'pending',
            ]);
        }

        return response()->json([
            'data' => $campaign->load('recipients'),
            'message' => "Campaña creada con {$contacts->count()} destinatarios preparados.",
        ], 201);
    }

    public function start(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $campaign = Campaign::where('organization_id', $organization->id)->findOrFail($id);

        $campaign->update([
            'status' => 'processing',
            'scheduled_at' => Carbon::now(),
        ]);

        return response()->json([
            'data' => $campaign,
            'message' => 'Campaña iniciada. Los mensajes se despacharán con intervalos anti-bloqueo.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $campaign = Campaign::where('organization_id', $organization->id)->findOrFail($id);

        $campaign->delete();

        return response()->json([
            'message' => 'Campaña eliminada exitosamente.',
        ]);
    }
}
