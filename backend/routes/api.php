<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MinutesTaskController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('auth:api')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/dashboard', [DashboardController::class, 'show']);
    Route::get('/minutes/tasks', [MinutesTaskController::class, 'index']);
    Route::post('/minutes/tasks', [MinutesTaskController::class, 'store']);
    Route::get('/minutes/tasks/{taskId}/result', [MinutesTaskController::class, 'result']);
    Route::get('/minutes/tasks/{taskId}', [MinutesTaskController::class, 'show']);
});
