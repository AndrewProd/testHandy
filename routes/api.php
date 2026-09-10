<?php

use App\Http\Controllers\Api\AiRequestController;
use App\Http\Controllers\Api\DripCampaignController;
use App\Http\Controllers\Api\FieldServiceController;
use App\Http\Controllers\Api\HarnessController;
use App\Http\Controllers\Api\ReplyTaskController;
use Illuminate\Support\Facades\Route;

Route::get('campaigns/meta', [DripCampaignController::class, 'meta']);
Route::patch('campaigns/{campaign}/status', [DripCampaignController::class, 'updateStatus']);
Route::apiResource('campaigns', DripCampaignController::class);

Route::get('ai-requests/summary', [AiRequestController::class, 'summary']);
Route::get('ai-requests', [AiRequestController::class, 'index']);

Route::get('reply-tasks', [ReplyTaskController::class, 'index']);
Route::get('reply-tasks/{replyTask}', [ReplyTaskController::class, 'show']);
Route::patch('reply-tasks/{replyTask}', [ReplyTaskController::class, 'update']);
Route::post('reply-tasks/{replyTask}/schedule-measurement', [ReplyTaskController::class, 'scheduleMeasurement']);
Route::post('reply-tasks/{replyTask}/messages', [ReplyTaskController::class, 'send']);
Route::post('reply-tasks/{replyTask}/copilot', [ReplyTaskController::class, 'copilot']);

Route::get('field/meta', [FieldServiceController::class, 'meta']);
Route::get('field/teams', [FieldServiceController::class, 'teams']);
Route::patch('field/teams/{team}', [FieldServiceController::class, 'updateTeam']);
Route::get('field/visits', [FieldServiceController::class, 'visits']);
Route::get('field/requests', [FieldServiceController::class, 'requests']);
Route::get('field/requests/{fieldRequest}', [FieldServiceController::class, 'showRequest']);
Route::get('field/requests/{fieldRequest}/offers', [FieldServiceController::class, 'offers']);
Route::post('field/requests/{fieldRequest}/schedule', [FieldServiceController::class, 'schedule']);

Route::get('harness/clients', [HarnessController::class, 'clients']);
Route::post('harness/publish', [HarnessController::class, 'publish']);
Route::get('harness/events/{eventId}', [HarnessController::class, 'show']);
Route::get('inbound/clients', [HarnessController::class, 'clients']);
Route::post('inbound/publish', [HarnessController::class, 'publish']);
Route::get('inbound/events/{eventId}', [HarnessController::class, 'show']);
