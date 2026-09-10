<?php

namespace App\Http\Controllers\Api;

use App\Domain\Conversations\Services\ReplyCopilot;
use App\Domain\Conversations\Services\SendManagerReply;
use App\Domain\FieldService\Enums\FieldServiceSource;
use App\Domain\FieldService\Enums\FieldServiceType;
use App\Domain\FieldService\Services\FieldDispatchService;
use App\Domain\FieldService\Services\FieldServiceRequestService;
use App\Domain\ReplyIntake\Enums\Intent;
use App\Domain\ReplyIntake\Services\ReplyTaskHistory;
use App\Http\Controllers\Controller;
use App\Http\Requests\CopilotRequest;
use App\Http\Requests\ScheduleMeasurementRequest;
use App\Http\Requests\SendManagerReplyRequest;
use App\Http\Requests\UpdateReplyTaskRequest;
use App\Http\Resources\FieldServiceRequestResource;
use App\Http\Resources\ReplyTaskResource;
use App\Models\ReplyTask;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use InvalidArgumentException;

class ReplyTaskController extends Controller
{
    public function __construct(
        private readonly FieldServiceRequestService $fieldRequests,
        private readonly FieldDispatchService $dispatch,
        private readonly ReplyTaskHistory $history,
        private readonly ReplyCopilot $copilot,
        private readonly SendManagerReply $replies,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ReplyTask::query()
            ->where('tenant_id', $this->tenantId($request))
            ->with(['client.enrollments', 'fieldServiceRequest.assignedTeam', 'fieldServiceRequest.latestVisit.team'])
            ->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('sentiment')) {
            $query->where('sentiment', $request->string('sentiment'));
        }

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->string('event_id'));
        }

        return ReplyTaskResource::collection($query->get());
    }

    public function show(Request $request, int $replyTask): ReplyTaskResource
    {
        $task = $this->find($request, $replyTask);

        return $this->present($task);
    }

    public function update(UpdateReplyTaskRequest $request, int $replyTask): ReplyTaskResource
    {
        $task = $this->find($request, $replyTask);
        $task->fill($request->validated())->save();

        return $this->present($task->fresh());
    }

    public function scheduleMeasurement(ScheduleMeasurementRequest $request, int $replyTask): JsonResponse
    {
        $task = $this->find($request, $replyTask);
        $intent = $task->intent ?? Intent::fromSentiment($task->sentiment);

        if ($intent !== null && !$intent->allowsManagerMeasurement()) {
            return response()->json([
                'message' => 'This reply cannot be turned into a field visit.',
            ], 422);
        }

        $data = $request->validated();
        $fsr = $this->fieldRequests->createFromReply(
            $task,
            $task->client,
            FieldServiceSource::Manager,
            FieldServiceType::tryFrom($data['type'] ?? '') ?? FieldServiceType::Measurement,
            $data,
        );

        if (!empty($data['team_id']) && !empty($data['scheduled_at'])) {
            try {
                $fsr = $this->fieldRequests->schedule(
                    $fsr,
                    (int) $data['team_id'],
                    Carbon::parse($data['scheduled_at']),
                );
            } catch (InvalidArgumentException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }
        }

        $task = $this->loadTask($task->fresh());
        $offers = $this->dispatch->offers($fsr);

        return response()->json([
            'data' => [
                'task' => new ReplyTaskResource($task),
                'field_service_request' => new FieldServiceRequestResource($fsr),
                'offers' => $offers,
                'events' => $this->history->for($task),
            ],
        ], $fsr->wasRecentlyCreated ? 201 : 200);
    }

    private function present(ReplyTask $task): ReplyTaskResource
    {
        $task = $this->loadTask($task);
        $extra = [
            'events' => $this->history->for($task),
            'copilot' => $this->copilot->snapshot($task),
            'mail_provider' => config('outbound_mail.driver', 'mailgun'),
        ];

        if ($task->fieldServiceRequest && $task->fieldServiceRequest->status->isOpen()) {
            $extra['offers'] = $this->dispatch->offers($task->fieldServiceRequest);
        }

        return (new ReplyTaskResource($task))->additional($extra);
    }

    private function loadTask(ReplyTask $task): ReplyTask
    {
        return $task->load([
            'client.enrollments',
            'messages',
            'fieldServiceRequest.assignedTeam',
            'fieldServiceRequest.visits.team',
            'fieldServiceRequest.latestVisit.team',
        ]);
    }

    public function send(SendManagerReplyRequest $request, int $replyTask): JsonResponse
    {
        $task = $this->find($request, $replyTask);

        try {
            $this->replies->handle($task, $request->validated('body'), $request->validated());
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'data' => new ReplyTaskResource($this->loadTask($task->fresh())),
            'copilot' => $this->copilot->snapshot($task->fresh()),
            'mail_provider' => config('outbound_mail.driver', 'mailgun'),
            'events' => $this->history->for($task->fresh()),
        ], 201);
    }

    public function copilot(CopilotRequest $request, int $replyTask): JsonResponse
    {
        $task = $this->loadTask($this->find($request, $replyTask));
        $action = $request->validated('action');
        $body = $request->validated('body');

        $text = match ($action) {
            'improve' => $this->copilot->improve($task, (string) $body),
            'summarize' => $this->copilot->summarize($task),
            'next_action' => $this->copilot->snapshot($task)['reason'],
            default => $this->copilot->draft($task, $body),
        };

        return response()->json([
            'data' => [
                'action' => $action,
                'text' => $text,
                'snapshot' => $this->copilot->snapshot($task),
            ],
        ]);
    }

    private function find(Request $request, int $id): ReplyTask
    {
        return ReplyTask::query()
            ->where('tenant_id', $this->tenantId($request))
            ->with('client')
            ->findOrFail($id);
    }

    private function tenantId(Request $request): int
    {
        return (int) ($request->header('X-Tenant-Id') ?: 42);
    }
}
