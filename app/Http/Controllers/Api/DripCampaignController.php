<?php

namespace App\Http\Controllers\Api;

use App\Domain\DripCampaign\Enums\CampaignStatus;
use App\Domain\DripCampaign\Enums\DeliveryChannel;
use App\Domain\DripCampaign\Enums\LeadStatus;
use App\Domain\ReplyIntake\Enums\Intent;
use App\Domain\DripCampaign\Services\DripCampaignService;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveDripCampaignRequest;
use App\Http\Resources\DripCampaignResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class DripCampaignController extends Controller
{
    public function __construct(
        private readonly DripCampaignService $campaigns,
    ) {}

    public function meta(): JsonResponse
    {
        return response()->json([
            'lead_statuses' => LeadStatus::options(),
            'channels' => DeliveryChannel::options(),
            'campaign_statuses' => CampaignStatus::options(),
            'intents' => Intent::options(),
        ]);
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        return DripCampaignResource::collection(
            $this->campaigns->listForTenant($this->tenantId($request)),
        );
    }

    public function store(SaveDripCampaignRequest $request): JsonResponse
    {
        $campaign = $this->campaigns->create(
            $request->tenantId(),
            $request->validated(),
        );

        return (new DripCampaignResource($campaign))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, int $campaign): DripCampaignResource
    {
        return new DripCampaignResource(
            $this->campaigns->findForTenant($this->tenantId($request), $campaign),
        );
    }

    public function update(SaveDripCampaignRequest $request, int $campaign): DripCampaignResource
    {
        $model = $this->campaigns->findForTenant($request->tenantId(), $campaign);

        return new DripCampaignResource(
            $this->campaigns->update($model, $request->validated()),
        );
    }

    public function updateStatus(Request $request, int $campaign): DripCampaignResource
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(CampaignStatus::class)],
        ]);

        $model = $this->campaigns->findForTenant($this->tenantId($request), $campaign);

        return new DripCampaignResource(
            $this->campaigns->changeStatus($model, CampaignStatus::from($data['status'])),
        );
    }

    public function destroy(Request $request, int $campaign): \Illuminate\Http\Response
    {
        $model = $this->campaigns->findForTenant($this->tenantId($request), $campaign);
        $this->campaigns->delete($model);

        return response()->noContent();
    }

    private function tenantId(Request $request): int
    {
        return (int) ($request->header('X-Tenant-Id') ?: 42);
    }
}
