<?php

use App\Http\Controllers\Api\AgentApiController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:api')->group(function () {
    Route::get('/v1/agents', [AgentApiController::class, 'agents']);
    Route::post('/v1/agents/{slug}/chat', [AgentApiController::class, 'chat']);
    Route::get('/v1/agents/{slug}/history', [AgentApiController::class, 'history']);
});
