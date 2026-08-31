<?php

namespace App\Modules\Companies\Http\Controllers;

use App\Modules\Companies\Actions\CreateCompanyAction;
use App\Modules\Companies\Actions\DeleteCompanyAction;
use App\Modules\Companies\Actions\UpdateCompanyAction;
use App\Modules\Companies\Http\Requests\CreateCompanyRequest;
use App\Modules\Companies\Http\Requests\ListCompaniesRequest;
use App\Modules\Companies\Http\Requests\UpdateCompanyRequest;
use App\Modules\Companies\Http\Resources\CompanyResource;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Queries\ListCompaniesQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CompanyController
{
    public function index(
        ListCompaniesRequest $request,
        ListCompaniesQuery $listCompaniesQuery,
    ): AnonymousResourceCollection {
        $this->authorize($request, 'viewAny', Company::class);

        $companies = $listCompaniesQuery->execute(
            $request->user(),
            $request->validated(),
        );

        return CompanyResource::collection($companies);
    }

    public function store(
        CreateCompanyRequest $request,
        CreateCompanyAction $createCompanyAction,
    ): JsonResponse {
        $this->authorize($request, 'create', Company::class);

        $company = $createCompanyAction->execute(
            $request->user(),
            $request->validated(),
        );

        return response()->json([
            'data' => (new CompanyResource($company))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ], 201);
    }

    public function show(Company $company): JsonResponse
    {
        $this->authorize(request(), 'view', $company);

        return response()->json([
            'data' => (new CompanyResource($company->load([
                'contacts',
                'deals.contact',
                'conversations.assignment.assignee',
            ])))->resolve(),
        ]);
    }

    public function update(
        UpdateCompanyRequest $request,
        Company $company,
        UpdateCompanyAction $updateCompanyAction,
    ): JsonResponse {
        $this->authorize($request, 'update', $company);

        $company = $updateCompanyAction->execute($company, $request->validated());

        return response()->json([
            'data' => (new CompanyResource($company))->resolve(),
            'message' => 'Registro guardado correctamente.',
        ]);
    }

    public function destroy(Company $company, DeleteCompanyAction $deleteCompanyAction): JsonResponse
    {
        $this->authorize(request(), 'delete', $company);

        $deleteCompanyAction->execute($company);

        return response()->json([
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    private function authorize($request, string $ability, mixed $arguments): void
    {
        abort_unless($request->user()?->can($ability, $arguments), 403);
    }
}
