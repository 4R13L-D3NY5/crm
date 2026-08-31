<?php

use App\Modules\Deals\Http\Controllers\DealController;
use App\Modules\Deals\Http\Controllers\DealStageController;
use App\Modules\Pipelines\Http\Controllers\PipelineBoardController;
use App\Modules\Pipelines\Http\Controllers\PipelineController;
use App\Modules\Pipelines\Http\Controllers\PipelineStageController;
use Illuminate\Support\Facades\Route;

Route::get('pipelines', [PipelineController::class, 'index']);
Route::post('pipelines', [PipelineController::class, 'store']);
Route::get('pipelines/{pipeline}/board', [PipelineBoardController::class, 'show']);
Route::post('pipelines/{pipeline}/stages', [PipelineStageController::class, 'store']);
Route::put('pipelines/{pipeline}/stages/{stage}', [PipelineStageController::class, 'update']);
Route::delete('pipelines/{pipeline}/stages/{stage}', [PipelineStageController::class, 'destroy']);

Route::apiResource('deals', DealController::class);
Route::put('deals/{deal}/stage', [DealStageController::class, 'update']);
