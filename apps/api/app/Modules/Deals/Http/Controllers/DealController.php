<?php

namespace App\Modules\Deals\Http\Controllers;

use App\Modules\Deals\Actions\CreateDealAction;
use App\Modules\Deals\Actions\DeleteDealAction;
use App\Modules\Deals\Actions\UpdateDealAction;
use App\Modules\Deals\Http\Requests\CreateDealRequest;
use App\Modules\Deals\Http\Requests\UpdateDealRequest;
use App\Modules\Deals\Http\Resources\DealResource;
use App\Modules\Deals\Models\Deal;
use Illuminate\Http\JsonResponse;

class DealController
{
    public function index(): JsonResponse
    {
        $request = request();
        $this->authorize($request, 'viewAny', Deal::class);

        $deals = Deal::query()
            ->where('organization_id', $request->user()->current_organization_id)
            ->with(['contact', 'company', 'stage'])
            ->latest()
            ->paginate((int) $request->integer('per_page', 15));

        return response()->json(DealResource::collection($deals)->response()->getData(true));
    }

    public function store(
        CreateDealRequest $request,
        CreateDealAction $createDealAction,
    ): JsonResponse {
        $this->authorize($request, 'create', Deal::class);

        $deal = $createDealAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => (new DealResource($deal))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ], 201);
    }

    public function show(Deal $deal): JsonResponse
    {
        $request = request();
        $this->authorize($request, 'view', $deal);

        return response()->json([
            'data' => (new DealResource($deal->load(['contact', 'company', 'stage', 'stageHistory'])))->resolve(),
        ]);
    }

    public function update(
        UpdateDealRequest $request,
        Deal $deal,
        UpdateDealAction $updateDealAction,
    ): JsonResponse {
        $deal = $updateDealAction->execute($deal, $request->validated());

        return response()->json([
            'data' => (new DealResource($deal))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ]);
    }

    public function destroy(Deal $deal, DeleteDealAction $deleteDealAction): JsonResponse
    {
        $request = request();
        $this->authorize($request, 'delete', $deal);

        $deleteDealAction->execute($deal);

        return response()->json([
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    private function authorize($request, string $ability, mixed $arguments): void
    {
        abort_unless($request->user()?->can($ability, $arguments), 403);
    }
}
