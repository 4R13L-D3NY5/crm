<?php

namespace App\Modules\Automations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automations\Models\ScheduledMessage;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduledMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $messages = ScheduledMessage::where('organization_id', $organization->id)
            ->when($request->filled('status') && $request->input('status') !== 'all', function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            })
            ->when($request->filled('date'), function ($q) use ($request) {
                $q->whereDate('scheduled_at', $request->input('date'));
            })
            ->with(['contact'])
            ->latest('scheduled_at')
            ->get();

        return response()->json(['data' => $messages]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'recipient_phone' => ['required', 'string', 'max:50'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:4000'],
            'scheduled_at' => ['required', 'date'],
            'whatsapp_account_id' => ['nullable', 'string', 'exists:whatsapp_accounts,id'],
        ]);

        $cleanPhone = preg_replace('/[^0-9+]/', '', $validated['recipient_phone']);

        // Buscar o crear contacto para vincular
        $contact = Contact::firstOrCreate(
            ['organization_id' => $organization->id, 'phone' => $cleanPhone],
            ['first_name' => $validated['contact_name'] ?? 'Contacto', 'status' => 'active']
        );

        $scheduledMessage = ScheduledMessage::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $validated['whatsapp_account_id'] ?? null,
            'recipient_phone' => $cleanPhone,
            'body' => $validated['body'],
            'scheduled_at' => Carbon::parse($validated['scheduled_at']),
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => $scheduledMessage->load('contact'),
            'message' => 'Mensaje programado con éxito.',
        ], 201);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $scheduledMessage = ScheduledMessage::where('organization_id', $organization->id)->findOrFail($id);

        $scheduledMessage->delete();

        return response()->json([
            'message' => 'Mensaje programado cancelado y eliminado.',
        ]);
    }
}
