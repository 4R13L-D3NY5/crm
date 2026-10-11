<?php

namespace App\Modules\Automations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automations\Models\ScheduledMessage;
use App\Modules\Automations\Services\ScheduledMessageDispatcher;
use App\Modules\Contacts\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class ScheduledMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $baseQuery = ScheduledMessage::where('organization_id', $organization->id);

        $messages = (clone $baseQuery)
            ->when($request->filled('status') && $request->input('status') !== 'all', function ($q) use ($request) {
                $q->where('status', $request->input('status'));
            })
            ->when($request->filled('date'), function ($q) use ($request) {
                $q->whereDate('scheduled_at', $request->input('date'));
            })
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->input('search') . '%';
                $q->where(function ($sub) use ($term) {
                    $sub->where('recipient_phone', 'ilike', $term)
                        ->orWhere('body', 'ilike', $term)
                        ->orWhereHas('contact', function ($c) use ($term) {
                            $c->where('first_name', 'ilike', $term)
                              ->orWhere('last_name', 'ilike', $term);
                        });
                });
            })
            ->with(['contact', 'whatsappAccount'])
            ->latest('scheduled_at')
            ->get();

        // Estadísticas para los contadores superiores
        $stats = [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', 'pending')->count(),
            'sent' => (clone $baseQuery)->where('status', 'sent')->count(),
            'failed' => (clone $baseQuery)->where('status', 'failed')->count(),
        ];

        return response()->json([
            'data' => $messages,
            'stats' => $stats,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'recipient_phone' => ['required', 'string', 'max:50'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'contact_id' => ['nullable', 'string', 'exists:contacts,id'],
            'body' => ['required', 'string', 'max:4000'],
            'scheduled_at' => ['required', 'date'],
            'whatsapp_account_id' => ['nullable', 'string', 'exists:whatsapp_accounts,id'],
        ]);

        $cleanPhone = preg_replace('/[^0-9+]/', '', $validated['recipient_phone']);

        $contact = null;
        if (!empty($validated['contact_id'])) {
            $contact = Contact::where('organization_id', $organization->id)->find($validated['contact_id']);
        }

        if (!$contact) {
            $contact = Contact::firstOrCreate(
                ['organization_id' => $organization->id, 'phone' => $cleanPhone],
                ['first_name' => $validated['contact_name'] ?? 'Contacto', 'status' => 'active']
            );
        }

        $setting = \App\Modules\Settings\Models\WorkspaceSetting::where('organization_id', $organization->id)->first();
        $tz = $setting?->timezone ?? 'America/La_Paz';

        $rawScheduledAt = $validated['scheduled_at'];
        $hasTz = str_contains($rawScheduledAt, 'Z') || preg_match('/[+-]\d{2}:\d{2}$/', $rawScheduledAt);
        $scheduledAt = $hasTz
            ? Carbon::parse($rawScheduledAt)->setTimezone('UTC')
            : Carbon::parse($rawScheduledAt, $tz)->setTimezone('UTC');

        $scheduledMessage = ScheduledMessage::create([
            'organization_id' => $organization->id,
            'contact_id' => $contact->id,
            'whatsapp_account_id' => $validated['whatsapp_account_id'] ?? null,
            'recipient_phone' => $cleanPhone,
            'body' => $validated['body'],
            'scheduled_at' => $scheduledAt,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => $scheduledMessage->load(['contact', 'whatsappAccount']),
            'message' => 'Mensaje programado con éxito.',
        ], 201);
    }

    public function sendNow(Request $request, string $id, ScheduledMessageDispatcher $dispatcher): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $scheduledMessage = ScheduledMessage::where('organization_id', $organization->id)
            ->with(['contact', 'whatsappAccount'])
            ->findOrFail($id);

        $success = $dispatcher->dispatch($scheduledMessage);

        if ($success) {
            return response()->json([
                'data' => $scheduledMessage->fresh(['contact', 'whatsappAccount']),
                'message' => 'Mensaje enviado de inmediato con éxito.',
            ]);
        }

        return response()->json([
            'data' => $scheduledMessage->fresh(['contact', 'whatsappAccount']),
            'message' => 'Hubo un error al despachar el mensaje: ' . ($scheduledMessage->error_message ?? 'Error desconocido'),
        ], 422);
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
