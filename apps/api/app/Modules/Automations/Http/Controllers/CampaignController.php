<?php

namespace App\Modules\Automations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Automations\Models\Campaign;
use App\Modules\Automations\Models\CampaignRecipient;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Parameters\Models\Category;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function show(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $campaign = Campaign::where('organization_id', $organization->id)
            ->with([
                'whatsappAccount',
                'recipients.contact.conversations' => function ($q) {
                    $q->orderByDesc('last_message_at')->orderByDesc('created_at');
                },
            ])
            ->findOrFail($id);

        return response()->json(['data' => $campaign]);
    }

    /**
     * Previsualizar el conteo y muestra de contactos destinatarios
     */
    public function previewRecipients(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'target_mode' => ['nullable', 'string', 'in:all,categories,tags,mixed'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['string'],
        ]);

        $targetMode = $validated['target_mode'] ?? 'all';
        $categoryIds = array_filter($validated['category_ids'] ?? []);
        $tagIds = array_filter($validated['tag_ids'] ?? []);

        $contacts = $this->buildTargetContactsQuery($organization->id, $targetMode, $categoryIds, $tagIds)
            ->with(['tags', 'categories'])
            ->get()
            ->unique(fn ($c) => preg_replace('/[^\d+]/', '', (string) $c->phone));

        $sample = $contacts->take(5)->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name ?: 'Sin Nombre',
            'phone' => $c->phone,
        ])->values();

        return response()->json([
            'count' => $contacts->count(),
            'samples' => $sample,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'whatsapp_account_id' => ['nullable', 'string', 'exists:whatsapp_accounts,id'],
            'message_template' => ['required', 'string', 'max:4000'],
            'delay_seconds' => ['nullable', 'integer', 'min:5', 'max:120'],
            'target_mode' => ['nullable', 'string', 'in:all,categories,tags,mixed'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string', 'exists:categories,id'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['string', 'exists:tags,id'],
        ]);

        $targetMode = $validated['target_mode'] ?? null;
        $categoryIds = array_filter($validated['category_ids'] ?? []);
        $tagIds = array_filter($validated['tag_ids'] ?? []);

        // Si targetMode no vino explícito, inferir según los arrays enviados
        if (! $targetMode) {
            if (! empty($categoryIds) && ! empty($tagIds)) {
                $targetMode = 'mixed';
            } elseif (! empty($categoryIds)) {
                $targetMode = 'categories';
            } elseif (! empty($tagIds)) {
                $targetMode = 'tags';
            } else {
                $targetMode = 'all';
            }
        }

        // Obtener contactos según criterios
        $contacts = $this->buildTargetContactsQuery($organization->id, $targetMode, $categoryIds, $tagIds)
            ->get()
            ->unique(fn ($c) => preg_replace('/[^\d+]/', '', (string) $c->phone));

        if ($contacts->isEmpty()) {
            return response()->json([
                'message' => 'No se encontraron contactos con teléfono para el segmento seleccionado.',
            ], 422);
        }

        // Generar resumen descriptivo del target
        $targetSummary = $this->buildTargetSummary($organization->id, $targetMode, $categoryIds, $tagIds);

        $campaign = Campaign::create([
            'organization_id' => $organization->id,
            'whatsapp_account_id' => $validated['whatsapp_account_id'] ?? null,
            'name' => $validated['name'],
            'status' => 'draft',
            'target_type' => $targetMode,
            'target_summary' => $targetSummary,
            'target_criteria' => [
                'target_mode' => $targetMode,
                'category_ids' => array_values($categoryIds),
                'tag_ids' => array_values($tagIds),
            ],
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
            'message' => "Campaña creada con {$contacts->count()} destinatarios preparados ({$targetSummary}).",
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

    public function pause(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $campaign = Campaign::where('organization_id', $organization->id)->findOrFail($id);

        $campaign->update([
            'status' => 'paused',
        ]);

        return response()->json([
            'data' => $campaign,
            'message' => 'Campaña pausada exitosamente.',
        ]);
    }

    public function resume(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $campaign = Campaign::where('organization_id', $organization->id)->findOrFail($id);

        $campaign->update([
            'status' => 'processing',
        ]);

        return response()->json([
            'data' => $campaign,
            'message' => 'Campaña reanudada.',
        ]);
    }

    public function retryFailed(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $campaign = Campaign::where('organization_id', $organization->id)->findOrFail($id);

        $failedRecipients = CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->get();

        if ($failedRecipients->isEmpty()) {
            return response()->json([
                'message' => 'No hay destinatarios fallidos en esta campaña.',
            ], 422);
        }

        $count = $failedRecipients->count();

        // Resetear destinatarios fallidos a estado pendiente
        CampaignRecipient::where('campaign_id', $campaign->id)
            ->where('status', 'failed')
            ->update([
                'status' => 'pending',
                'error_message' => null,
                'sent_at' => null,
            ]);

        $campaign->update([
            'status' => 'processing',
            'failed_count' => max(0, $campaign->failed_count - $count),
            'scheduled_at' => Carbon::now(),
        ]);

        return response()->json([
            'data' => $campaign->fresh(),
            'message' => "Se han reactivado {$count} destinatarios fallidos para reintento inmediato.",
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

    /**
     * Construye la consulta de contactos según el modo de segmentación
     */
    protected function buildTargetContactsQuery(string $organizationId, string $targetMode, array $categoryIds = [], array $tagIds = []): Builder
    {
        $query = Contact::query()
            ->where('organization_id', $organizationId)
            ->whereNotNull('phone')
            ->where('phone', '!=', '');

        // Filtrar por Categorías (si aplica)
        if ($targetMode === 'categories' || $targetMode === 'mixed' || (! empty($categoryIds) && empty($tagIds))) {
            if (! empty($categoryIds)) {
                $allCategoryIds = $this->resolveCategoryAndDescendantIds($organizationId, $categoryIds);
                $query->where(function (Builder $sub) use ($allCategoryIds) {
                    $sub->whereHas('categories', fn ($catQ) => $catQ->whereIn('categories.id', $allCategoryIds))
                        ->orWhereHas('conversations.categories', fn ($catQ) => $catQ->whereIn('categories.id', $allCategoryIds));
                });
            }
        }

        // Filtrar por Etiquetas (si aplica)
        if ($targetMode === 'tags' || $targetMode === 'mixed' || (! empty($tagIds) && empty($categoryIds))) {
            if (! empty($tagIds)) {
                $query->whereHas('tags', function (Builder $tagQ) use ($tagIds) {
                    $tagQ->whereIn('tags.id', $tagIds);
                });
            }
        }

        return $query;
    }

    /**
     * Resuelve los IDs de las categorías seleccionadas más todos sus descendientes jerárquicos
     */
    protected function resolveCategoryAndDescendantIds(string $organizationId, array $categoryIds): array
    {
        $allIds = collect($categoryIds)->filter()->unique()->values();
        if ($allIds->isEmpty()) {
            return [];
        }

        $currentLevel = $allIds->all();
        $accumulated = $allIds->all();

        for ($depth = 0; $depth < 10; $depth++) {
            $childIdsFromPivot = DB::table('category_parents')
                ->whereIn('parent_id', $currentLevel)
                ->pluck('category_id')
                ->all();

            $childIdsFromParentCol = DB::table('categories')
                ->where('organization_id', $organizationId)
                ->whereIn('parent_id', $currentLevel)
                ->pluck('id')
                ->all();

            $newChildren = array_diff(array_unique(array_merge($childIdsFromPivot, $childIdsFromParentCol)), $accumulated);
            if (empty($newChildren)) {
                break;
            }

            $accumulated = array_merge($accumulated, $newChildren);
            $currentLevel = $newChildren;
        }

        return array_values(array_unique($accumulated));
    }

    /**
     * Construye un resumen legible para la UI sobre los criterios elegidos
     */
    protected function buildTargetSummary(string $organizationId, string $targetMode, array $categoryIds = [], array $tagIds = []): string
    {
        if ($targetMode === 'all' || (empty($categoryIds) && empty($tagIds))) {
            return 'Toda la base de contactos';
        }

        $parts = [];

        if (! empty($categoryIds)) {
            $catNames = Category::where('organization_id', $organizationId)
                ->whereIn('id', $categoryIds)
                ->pluck('name')
                ->take(3)
                ->all();

            $catSummary = implode(', ', $catNames);
            if (count($categoryIds) > 3) {
                $catSummary .= ' (+' . (count($categoryIds) - 3) . ' más)';
            }
            $parts[] = 'Categorías: ' . $catSummary;
        }

        if (! empty($tagIds)) {
            $tagNames = Tag::where('organization_id', $organizationId)
                ->whereIn('id', $tagIds)
                ->pluck('name')
                ->take(3)
                ->all();

            $tagSummary = implode(', ', $tagNames);
            if (count($tagIds) > 3) {
                $tagSummary .= ' (+' . (count($tagIds) - 3) . ' más)';
            }
            $parts[] = 'Etiquetas: ' . $tagSummary;
        }

        return implode(' | ', $parts);
    }
}
