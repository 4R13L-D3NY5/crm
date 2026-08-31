<?php

use App\Modules\Audit\Http\Controllers\AuditLogController;
use App\Modules\Audit\Http\Controllers\AuditSummaryController;
use Illuminate\Support\Facades\Route;

Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('api.audit-logs.index');
Route::get('/audit-logs/summary', [AuditSummaryController::class, 'summary'])->name('api.audit-logs.summary');
Route::get('/audit-logs/export', [AuditSummaryController::class, 'export'])->name('api.audit-logs.export');
