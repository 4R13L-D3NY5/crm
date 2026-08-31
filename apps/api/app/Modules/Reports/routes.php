<?php

use App\Modules\Reports\Http\Controllers\DashboardReportController;
use App\Modules\Reports\Http\Controllers\ReportsAnalyticsController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/dashboard', DashboardReportController::class)->name('api.reports.dashboard');
Route::get('/reports/analytics', [ReportsAnalyticsController::class, 'analytics'])->name('api.reports.analytics');
Route::get('/reports/csat', [ReportsAnalyticsController::class, 'csat'])->name('api.reports.csat');
Route::get('/reports/export', [ReportsAnalyticsController::class, 'export'])->name('api.reports.export');
