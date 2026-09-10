<?php

namespace App\Http\Controllers\Api;

use App\Domain\AiAudit\Enums\AiPurpose;
use App\Domain\AiAudit\Enums\AiRequestStatus;
use App\Domain\AiAudit\Models\AiRequest;
use App\Http\Controllers\Controller;
use App\Http\Resources\AiRequestResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class AiRequestController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AiRequest::query()
            ->where('tenant_id', $this->tenantId($request))
            ->latest('created_at');

        foreach (['purpose', 'model', 'status', 'prompt_version', 'provider'] as $field) {
            if ($request->filled($field)) {
                $query->where($field, $request->string($field));
            }
        }

        if ($request->filled('search')) {
            $term = '%' . $request->string('search') . '%';
            $query->where(fn ($q) => $q
                ->where('user_prompt', 'ilike', $term)
                ->orWhere('system_prompt', 'ilike', $term)
                ->orWhere('error_message', 'ilike', $term));
        }

        return AiRequestResource::collection(
            $query->paginate((int) $request->integer('per_page', 25)),
        );
    }

    public function summary(Request $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);
        $days = max(1, (int) $request->integer('days', 14));
        $since = now()->subDays($days);

        $base = AiRequest::query()
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $since);

        $counts = (clone $base)
            ->selectRaw('count(*) as total')
            ->selectRaw("count(*) filter (where status = ?) as completed", [AiRequestStatus::Completed->value])
            ->selectRaw("count(*) filter (where status = ?) as failed", [AiRequestStatus::Failed->value])
            ->selectRaw("count(*) filter (where status = ?) as pending", [AiRequestStatus::Pending->value])
            ->first();

        $tokens = (clone $base)
            ->selectRaw('coalesce(sum(input_tokens),0) as input')
            ->selectRaw('coalesce(sum(output_tokens),0) as output')
            ->selectRaw('coalesce(sum(total_tokens),0) as total')
            ->first();

        $cost = (float) (clone $base)->sum('cost_usd');

        $byModel = (clone $base)
            ->select('model', DB::raw('count(*) as requests'), DB::raw('coalesce(sum(cost_usd),0) as cost_usd'))
            ->groupBy('model')
            ->orderByDesc('requests')
            ->get();

        $byPromptVersion = (clone $base)
            ->select('prompt_version')
            ->selectRaw('count(*) as requests')
            ->selectRaw('round(avg(latency_ms)) as avg_latency_ms')
            ->selectRaw('round(avg(total_tokens)) as avg_total_tokens')
            ->selectRaw('coalesce(sum(cost_usd),0) as cost_usd')
            ->selectRaw("round(100.0 * count(*) filter (where status = 'completed') / nullif(count(*),0), 1) as success_rate")
            ->whereNotNull('prompt_version')
            ->groupBy('prompt_version')
            ->orderByDesc('requests')
            ->get();

        return response()->json([
            'days' => $days,
            'requests' => [
                'total' => (int) $counts->total,
                'completed' => (int) $counts->completed,
                'failed' => (int) $counts->failed,
                'pending' => (int) $counts->pending,
            ],
            'tokens' => [
                'input' => (int) $tokens->input,
                'output' => (int) $tokens->output,
                'total' => (int) $tokens->total,
            ],
            'cost_usd' => round($cost, 6),
            'by_model' => $byModel->map(fn ($r) => [
                'model' => $r->model,
                'requests' => (int) $r->requests,
                'cost_usd' => round((float) $r->cost_usd, 6),
            ]),
            'by_prompt_version' => $byPromptVersion->map(fn ($r) => [
                'prompt_version' => $r->prompt_version,
                'requests' => (int) $r->requests,
                'avg_latency_ms' => (int) $r->avg_latency_ms,
                'avg_total_tokens' => (int) $r->avg_total_tokens,
                'cost_usd' => round((float) $r->cost_usd, 6),
                'success_rate' => (float) $r->success_rate,
            ]),
            'filters' => [
                'purposes' => AiPurpose::values(),
                'statuses' => AiRequestStatus::values(),
                'models' => $byModel->pluck('model')->values(),
            ],
        ]);
    }

    private function tenantId(Request $request): int
    {
        return (int) $request->integer('tenant_id', 42);
    }
}
