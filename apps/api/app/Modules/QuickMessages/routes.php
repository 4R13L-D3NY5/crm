<?php

use App\Modules\QuickMessages\Http\Controllers\QuickMessageController;
use Illuminate\Support\Facades\Route;

Route::apiResource('quick-messages', QuickMessageController::class);
