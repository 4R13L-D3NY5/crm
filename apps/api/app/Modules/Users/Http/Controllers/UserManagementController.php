<?php

namespace App\Modules\Users\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $query = User::whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id));

        if ($request->user()?->email !== 'admin@crm.local') {
            $query->where('users.email', '!=', 'admin@crm.local');
        }

        $users = $query->with(['queues', 'categories', 'whatsappAccounts'])
            ->get()
            ->map(function ($u) use ($organization) {
                $role = $u->organizations()->where('organizations.id', $organization->id)->first()?->pivot?->role ?? 'agent';
                return [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                    'role' => $role,
                    'presence_status' => $u->presence_status ?? 'offline',
                    'last_seen_at' => $u->last_seen_at?->toIso8601String(),
                    'queues' => $u->queues->map(fn ($q) => ['id' => $q->id, 'name' => $q->name, 'color' => $q->color]),
                    'categories' => $u->categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'color' => $c->color]),
                    'channels' => $u->whatsappAccounts->where('organization_id', $organization->id)->map(fn ($ch) => [
                        'id' => $ch->id,
                        'name' => $ch->name,
                        'session_type' => $ch->session_type,
                        'display_phone_number' => $ch->display_phone_number,
                        'status' => $ch->status,
                    ])->values(),
                ];
            });

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $currentUsersCount = $organization->users()->where('users.email', '!=', 'admin@crm.local')->count();
        $maxAgents = $organization->max_agents ?? 3;

        if ($currentUsersCount >= $maxAgents) {
            return response()->json([
                'message' => "Has alcanzado el límite máximo de {$maxAgents} operadores/agentes permitidos en tu suscripción (Plan " . ucfirst($organization->plan ?? 'básico') . "). Contacta a soporte para adquirir agentes adicionales.",
                'code' => 'QUOTA_AGENTS_EXCEEDED',
                'current_count' => $currentUsersCount,
                'max_allowed' => $maxAgents,
            ], 422);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:admin,supervisor,agent'],
            'queue_ids' => ['nullable', 'array'],
            'queue_ids.*' => ['exists:queues,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => ['exists:whatsapp_accounts,id'],
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'current_organization_id' => $organization->id,
            'presence_status' => 'offline',
        ]);

        $user->organizations()->attach($organization->id, [
            'id' => (string) Str::ulid(),
            'role' => $validated['role'],
        ]);

        if (!empty($validated['queue_ids'])) {
            $user->queues()->sync($validated['queue_ids']);
        }

        if (isset($validated['category_ids'])) {
            $syncData = [];
            foreach ($validated['category_ids'] as $catId) {
                $syncData[$catId] = ['organization_id' => $organization->id];
            }
            $user->categories()->sync($syncData);
        }

        if (!empty($validated['channel_ids'])) {
            $syncChannels = [];
            foreach ($validated['channel_ids'] as $chId) {
                $syncChannels[$chId] = [
                    'id' => (string) Str::ulid(),
                    'can_view' => true,
                    'can_reply' => true,
                ];
            }
            $user->whatsappAccounts()->sync($syncChannels);
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $validated['role'],
                'presence_status' => 'offline',
                'queues' => $user->queues,
                'categories' => $user->categories,
                'channels' => $user->whatsappAccounts()->where('organization_id', $organization->id)->get()->map(fn ($ch) => [
                    'id' => $ch->id,
                    'name' => $ch->name,
                    'session_type' => $ch->session_type,
                    'display_phone_number' => $ch->display_phone_number,
                    'status' => $ch->status,
                ]),
            ],
            'message' => 'Operador creado exitosamente.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $user = User::whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id))->findOrFail($id);

        if ($user->email === 'admin@crm.local' && $request->user()?->email !== 'admin@crm.local') {
            return response()->json([
                'message' => 'No tienes permisos para modificar a la cuenta maestra de la plataforma.',
            ], 403);
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,supervisor,agent'],
            'queue_ids' => ['nullable', 'array'],
            'queue_ids.*' => ['exists:queues,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['exists:categories,id'],
            'channel_ids' => ['nullable', 'array'],
            'channel_ids.*' => ['exists:whatsapp_accounts,id'],
        ]);

        if (isset($validated['name'])) {
            $user->update(['name' => $validated['name']]);
        }

        if (isset($validated['role'])) {
            $user->organizations()->updateExistingPivot($organization->id, ['role' => $validated['role']]);
        }

        if (isset($validated['queue_ids'])) {
            $user->queues()->sync($validated['queue_ids']);
        }

        if (isset($validated['category_ids'])) {
            $syncData = [];
            foreach ($validated['category_ids'] as $catId) {
                $syncData[$catId] = ['organization_id' => $organization->id];
            }
            $user->categories()->sync($syncData);
        }

        if (isset($validated['channel_ids'])) {
            $syncChannels = [];
            foreach ($validated['channel_ids'] as $chId) {
                $syncChannels[$chId] = [
                    'id' => (string) Str::ulid(),
                    'can_view' => true,
                    'can_reply' => true,
                ];
            }
            $user->whatsappAccounts()->sync($syncChannels);
        }

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $validated['role'] ?? $user->currentOrganizationRole(),
                'queues' => $user->queues,
                'categories' => $user->categories,
                'channels' => $user->whatsappAccounts()->where('organization_id', $organization->id)->get()->map(fn ($ch) => [
                    'id' => $ch->id,
                    'name' => $ch->name,
                    'session_type' => $ch->session_type,
                    'display_phone_number' => $ch->display_phone_number,
                    'status' => $ch->status,
                ]),
            ],
            'message' => 'Operador actualizado correctamente.',
        ]);
    }

    public function updatePresence(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $user = User::whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id))->findOrFail($id);

        $validated = $request->validate([
            'presence_status' => ['required', 'string', 'in:online,busy,offline'],
        ]);

        $user->update([
            'presence_status' => $validated['presence_status'],
            'last_seen_at' => Carbon::now(),
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'presence_status' => $user->presence_status,
                'last_seen_at' => $user->last_seen_at->toIso8601String(),
            ],
            'message' => 'Estado de presencia actualizado.',
        ]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $user = User::whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id))->findOrFail($id);

        if ($user->email === 'admin@crm.local' && $request->user()?->email !== 'admin@crm.local') {
            return response()->json([
                'message' => 'No tienes permisos para desvincular a la cuenta maestra de la plataforma.',
            ], 403);
        }

        $user->organizations()->detach($organization->id);

        return response()->json(['message' => 'Operador desvinculado de la organización.']);
    }
}
