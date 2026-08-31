<?php

namespace App\Modules\Pipelines\Http\Controllers;

use App\Modules\Pipelines\Http\Resources\PipelineResource;
use App\Modules\Pipelines\Models\Pipeline;
use Illuminate\Http\JsonResponse;

class PipelineBoardController
{
    public function show(Pipeline $pipeline): JsonResponse
    {
        $request = request();
        abort_unless($request->user()?->can('view', $pipeline), 403);

        $board = $pipeline->load([
            'stages.deals' => fn ($query) => $query
                ->where('organization_id', $request->user()->current_organization_id)
                ->with(['contact', 'company'])
                ->orderByDesc('updated_at'),
        ]);

        return response()->json([
            'data' => (new PipelineResource($board))->resolve(),
        ]);
    }
}
