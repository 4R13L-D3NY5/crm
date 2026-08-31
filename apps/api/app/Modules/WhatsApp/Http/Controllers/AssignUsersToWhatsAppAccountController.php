<?php

namespace App\Modules\WhatsApp\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AssignUsersToWhatsAppAccountController extends Controller
{
    public function __invoke(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $account = WhatsAppAccount::where('organization_id', $organization->id)->findOrFail($id);

        $validated = $request->validate([
            'users' => ['required', 'array'],
            'users.*.user_id' => ['required', 'string', 'exists:users,id'],
            'users.*.can_view' => ['boolean'],
            'users.*.can_reply' => ['boolean'],
        ]);

        $syncData = [];
        foreach ($validated['users'] as $u) {
            $syncData[$u['user_id']] = [
                'id' => (string) Str::ulid(),
                'can_view' => $u['can_view'] ?? true,
                'can_reply' => $u['can_reply'] ?? true,
            ];
        }

        $account->authorizedUsers()->sync($syncData);

        return response()->json([
            'data' => $account->load('authorizedUsers'),
            'message' => 'Operadores asignados a la conexión exitosamente.',
        ]);
    }
}
