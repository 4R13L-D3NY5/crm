<?php

namespace App\Modules\Deals\Http\Controllers;

use App\Modules\Deals\Actions\MoveDealStageAction;
use App\Modules\Deals\Http\Requests\MoveDealStageRequest;
use App\Modules\Deals\Http\Resources\DealResource;
use App\Modules\Deals\Models\Deal;
use App\Modules\Pipelines\Models\PipelineStage;
use Illuminate\Http\JsonResponse;

class DealStageController
{
    public function update(
        MoveDealStageRequest $request,
        Deal $deal,
        MoveDealStageAction $moveDealStageAction,
    ): JsonResponse {
        $targetStage = PipelineStage::query()->findOrFail($request->validated('pipeline_stage_id'));

        abort_unless($targetStage->pipeline_id === $deal->pipeline_id, 422, 'La etapa no pertenece al pipeline del deal.');

        $deal = $moveDealStageAction->execute($request->user(), $deal, $targetStage);

        return response()->json([
            'data' => (new DealResource($deal))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ]);
    }
}
