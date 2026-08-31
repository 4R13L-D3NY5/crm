<?php

namespace App\Modules\Automations\Http\Controllers;

use App\Modules\Automations\Actions\CreateAutomationRuleAction;
use App\Modules\Automations\Actions\DeleteAutomationRuleAction;
use App\Modules\Automations\Actions\UpdateAutomationRuleAction;
use App\Modules\Automations\Http\Requests\CreateAutomationRuleRequest;
use App\Modules\Automations\Http\Requests\ListAutomationRulesRequest;
use App\Modules\Automations\Http\Requests\UpdateAutomationRuleRequest;
use App\Modules\Automations\Http\Resources\AutomationRuleResource;
use App\Modules\Automations\Models\AutomationRule;
use App\Modules\Automations\Queries\ListAutomationRulesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AutomationRuleController
{
    public function index(
        ListAutomationRulesRequest $request,
        ListAutomationRulesQuery $listAutomationRulesQuery,
    ): AnonymousResourceCollection {
        $this->authorize($request, 'viewAny', AutomationRule::class);

        $rules = $listAutomationRulesQuery->execute($request->user(), $request->validated());

        return AutomationRuleResource::collection($rules);
    }

    public function store(
        CreateAutomationRuleRequest $request,
        CreateAutomationRuleAction $createAutomationRuleAction,
    ): JsonResponse {
        $this->authorize($request, 'create', AutomationRule::class);

        $rule = $createAutomationRuleAction->execute($request->user(), $request->validated());

        return response()->json([
            'data' => (new AutomationRuleResource($rule))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ], 201);
    }

    public function update(
        UpdateAutomationRuleRequest $request,
        AutomationRule $automationRule,
        UpdateAutomationRuleAction $updateAutomationRuleAction,
    ): JsonResponse {
        $rule = $updateAutomationRuleAction->execute($automationRule, $request->validated());

        return response()->json([
            'data' => (new AutomationRuleResource($rule))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ]);
    }

    public function destroy(
        AutomationRule $automationRule,
        DeleteAutomationRuleAction $deleteAutomationRuleAction,
    ): JsonResponse {
        $request = request();
        $this->authorize($request, 'delete', $automationRule);

        $deleteAutomationRuleAction->execute($automationRule);

        return response()->json([
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    private function authorize($request, string $ability, mixed $arguments): void
    {
        abort_unless($request->user()?->can($ability, $arguments), 403);
    }
}
