<?php

use App\Modules\Tenancy\Http\Controllers\SuperAdminTenantController;
use Illuminate\Support\Facades\Route;

Route::prefix('superadmin')->name('api.superadmin.')->group(function () {
    Route::get('/tenants', [SuperAdminTenantController::class, 'index'])->name('tenants.index');
    Route::post('/tenants/{id}/renew', [SuperAdminTenantController::class, 'renew'])->name('tenants.renew');
    Route::post('/tenants/{id}/proration-preview', [SuperAdminTenantController::class, 'previewProration'])->name('tenants.proration.preview');
    Route::post('/tenants/{id}/change-plan', [SuperAdminTenantController::class, 'changePlan'])->name('tenants.change_plan');
    Route::post('/tenants/{id}/adjust-quotas', [SuperAdminTenantController::class, 'adjustQuotas'])->name('tenants.adjust_quotas');
    Route::post('/tenants/{id}/toggle-status', [SuperAdminTenantController::class, 'toggleSuspension'])->name('tenants.toggle_status');
    Route::post('/tenants/{id}/generate-token', [SuperAdminTenantController::class, 'generateToken'])->name('tenants.generate_token');
    Route::post('/tenants/{id}/ai-reminder', [SuperAdminTenantController::class, 'aiReminder'])->name('tenants.ai_reminder');
    Route::post('/tenants/{id}/ai-audit', [SuperAdminTenantController::class, 'aiAudit'])->name('tenants.ai_audit');
});
