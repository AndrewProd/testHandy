<?php

namespace App\Http\Controllers\Api;

use App\Domain\FieldService\Enums\FieldServiceRequestStatus;
use App\Domain\FieldService\Enums\FieldServiceSource;
use App\Domain\FieldService\Enums\FieldServiceType;
use App\Domain\FieldService\Enums\FieldVisitStatus;
use App\Domain\FieldService\Services\FieldDispatchService;
use App\Domain\FieldService\Services\FieldServiceRequestService;
use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleFieldVisitRequest;
use App\Http\Requests\UpdateFieldTeamRequest;
use App\Http\Resources\FieldServiceRequestResource;
use App\Http\Resources\FieldTeamResource;
use App\Http\Resources\FieldVisitResource;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class FieldServiceController extends Controller
{
    public function __construct(
        private readonly FieldServiceRequestService $fieldRequests,
        private readonly FieldDispatchService $dispatch,
    ) {}

    public function meta(): JsonResponse
    {
        return response()->json([
            'types' => FieldServiceType::options(),
            'request_statuses' => FieldServiceRequestStatus::options(),
            'visit_statuses' => FieldVisitStatus::options(),
            'sources' => FieldServiceSource::options(),
        ]);
    }

    public function teams(Request $request): AnonymousResourceCollection
    {
        return FieldTeamResource::collection(
            $this->fieldRequests->listTeams($this->tenantId($request)),
        );
    }

    public function updateTeam(UpdateFieldTeamRequest $request, int $team): FieldTeamResource
    {
        $model = $this->fieldRequests->findTeam($this->tenantId($request), $team);

        return new FieldTeamResource(
            $this->fieldRequests->updateTeam($model, $request->validated()),
        );
    }

    public function requests(Request $request): AnonymousResourceCollection
    {
        return FieldServiceRequestResource::collection(
            $this->fieldRequests->listForTenant($this->tenantId($request)),
        );
    }

    public function showRequest(Request $request, int $fieldRequest): JsonResponse
    {
        $model = $this->fieldRequests->findForTenant($this->tenantId($request), $fieldRequest);

        return response()->json([
            'data' => new FieldServiceRequestResource($model),
            'offers' => $this->dispatch->offers($model),
        ]);
    }

    public function offers(Request $request, int $fieldRequest): JsonResponse
    {
        $model = $this->fieldRequests->findForTenant($this->tenantId($request), $fieldRequest);
        $at = $request->filled('scheduled_at') ? Carbon::parse($request->string('scheduled_at')) : null;

        return response()->json([
            'data' => $this->dispatch->offers($model, $at),
        ]);
    }

    public function schedule(ScheduleFieldVisitRequest $request, int $fieldRequest): JsonResponse
    {
        $model = $this->fieldRequests->findForTenant($this->tenantId($request), $fieldRequest);

        try {
            $model = $this->fieldRequests->schedule(
                $model,
                (int) $request->validated('team_id'),
                Carbon::parse($request->validated('scheduled_at')),
            );
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => new FieldServiceRequestResource($model),
        ]);
    }

    public function visits(Request $request): AnonymousResourceCollection
    {
        return FieldVisitResource::collection(
            $this->fieldRequests->listVisits($this->tenantId($request)),
        );
    }

    private function tenantId(Request $request): int
    {
        return (int) ($request->header('X-Tenant-Id') ?: 42);
    }
}
