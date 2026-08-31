<?php

namespace App\Modules\Pipelines\Http\Controllers;

use App\Modules\Pipelines\Actions\CreatePipelineAction;
use App\Modules\Pipelines\Http\Requests\CreatePipelineRequest;
use App\Modules\Pipelines\Http\Resources\PipelineResource;
use App\Modules\Pipelines\Models\Pipeline;
use Illuminate\Http\JsonResponse;

class PipelineController
{
    public function index(): JsonResponse
    {
        $request = request();
        $this->authorize($request, 'viewAny', Pipeline::class);

        $pipelines = Pipeline::query()
            ->where('organization_id', $request->user()->current_organization_id)
            ->with('stages')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => PipelineResource::collection($pipelines)->resolve(),
        ]);
    }

    public function store(
        CreatePipelineRequest $request,
        CreatePipelineAction $createPipelineAction,
    ): JsonResponse {
        $this->authorize($request, 'create', Pipeline::class);

        $pipeline = $createPipelineAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => (new PipelineResource($pipeline->load('stages')))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ], 201);
    }

    private function authorize($request, string $ability, mixed $arguments): void
    {
        abort_unless($request->user()?->can($ability, $arguments), 403);
    }
}
