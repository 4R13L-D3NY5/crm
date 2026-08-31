<?php

namespace App\Modules\Pipelines\Http\Controllers;

use App\Modules\Pipelines\Actions\CreatePipelineStageAction;
use App\Modules\Pipelines\Actions\DeletePipelineStageAction;
use App\Modules\Pipelines\Actions\UpdatePipelineStageAction;
use App\Modules\Pipelines\Http\Requests\CreatePipelineStageRequest;
use App\Modules\Pipelines\Http\Requests\UpdatePipelineStageRequest;
use App\Modules\Pipelines\Http\Resources\PipelineStageResource;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use Illuminate\Http\JsonResponse;

class PipelineStageController
{
    public function store(
        CreatePipelineStageRequest $request,
        Pipeline $pipeline,
        CreatePipelineStageAction $createPipelineStageAction,
    ): JsonResponse {
        $this->authorizePipeline($request, $pipeline);

        $stage = $createPipelineStageAction->execute($pipeline, $request->validated());

        return response()->json([
            'data' => (new PipelineStageResource($stage))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ], 201);
    }

    public function update(
        UpdatePipelineStageRequest $request,
        Pipeline $pipeline,
        PipelineStage $stage,
        UpdatePipelineStageAction $updatePipelineStageAction,
    ): JsonResponse {
        $this->authorizePipeline($request, $pipeline);
        abort_unless($stage->pipeline_id === $pipeline->getKey(), 404);

        $stage = $updatePipelineStageAction->execute($stage, $request->validated());

        return response()->json([
            'data' => (new PipelineStageResource($stage))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ]);
    }

    public function destroy(
        Pipeline $pipeline,
        PipelineStage $stage,
        DeletePipelineStageAction $deletePipelineStageAction,
    ): JsonResponse {
        $request = request();
        $this->authorizePipeline($request, $pipeline);
        abort_unless($stage->pipeline_id === $pipeline->getKey(), 404);

        $deletePipelineStageAction->execute($stage);

        return response()->json([
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    private function authorizePipeline($request, Pipeline $pipeline): void
    {
        abort_unless($request->user()?->can('view', $pipeline), 403);
    }
}
