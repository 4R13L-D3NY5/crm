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

        $users = User::whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id))
            ->with(['queues'])
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
                ];
            });

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'in:admin,supervisor,agent'],
            'queue_ids' => ['nullable', 'array'],
            'queue_ids.*' => ['exists:queues,id'],
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

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $validated['role'],
                'presence_status' => 'offline',
                'queues' => $user->queues,
            ],
            'message' => 'Operador creado exitosamente.',
        ], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $user = User::whereHas('organizations', fn ($q) => $q->where('organizations.id', $organization->id))->findOrFail($id);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'in:admin,supervisor,agent'],
            'queue_ids' => ['nullable', 'array'],
            'queue_ids.*' => ['exists:queues,id'],
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

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $validated['role'] ?? $user->currentOrganizationRole(),
                'queues' => $user->queues,
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

        $user->organizations()->detach($organization->id);

        return response()->json(['message' => 'Operador desvinculado de la organización.']);
    }
}
