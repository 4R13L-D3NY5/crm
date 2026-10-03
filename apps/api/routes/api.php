<?php

use App\Modules\Auth\Http\Controllers\AuthenticatedSessionController;
use App\Modules\Auth\Http\Controllers\CurrentUserController;
use App\Modules\Tenancy\Http\Controllers\CurrentOrganizationController;
use App\Modules\Tenancy\Http\Controllers\OrganizationController;
use Illuminate\Support\Facades\Route;

Route::get('/health', static function () {
    return response()->json([
        'data' => [
            'status' => 'ok',
            'app' => config('app.name'),
            'environment' => app()->environment(),
        ],
    ]);
})->name('api.health');

Route::get('/docs/openapi.json', [\App\Modules\Docs\Http\Controllers\OpenApiDocsController::class, 'openApiJson'])->name('api.docs.openapi');

require app_path('Modules/WhatsApp/webhook-routes.php');
Route::get('social/comments/webhook', [\App\Modules\Social\Http\Controllers\SocialCommentController::class, 'verify'])->name('social.comments.webhook.verify');
Route::post('social/comments/webhook', [\App\Modules\Social\Http\Controllers\SocialCommentController::class, 'handleWebhook'])->name('social.comments.webhook');

Route::middleware('web')->group(function (): void {
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('api.login');

    Route::middleware(['auth:sanctum', 'current.organization'])->group(function (): void {
        Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('api.logout');
        Route::get('/me', [CurrentUserController::class, 'show'])->name('api.me');
        Route::get('/organizations', [OrganizationController::class, 'index'])->name('api.organizations.index');
        Route::get('/organizations/current', [CurrentOrganizationController::class, 'index'])->name('api.organizations.current');
        Route::put('/organizations/current', [CurrentOrganizationController::class, 'update'])->name('api.organizations.switch');
        require app_path('Modules/AiAgent/routes.php');
        require app_path('Modules/HentleAi/routes.php');
        require app_path('Modules/Audit/routes.php');
        require app_path('Modules/Automations/routes.php');
        require app_path('Modules/Companies/routes.php');
        require app_path('Modules/Conversations/routes.php');
        require app_path('Modules/Contacts/routes.php');
        require app_path('Modules/Pipelines/routes.php');
        require app_path('Modules/Queues/routes.php');
        require app_path('Modules/QuickMessages/routes.php');
        require app_path('Modules/Reports/routes.php');
        require app_path('Modules/Settings/routes.php');
        require app_path('Modules/Social/routes.php');
        require app_path('Modules/Users/routes.php');
        require app_path('Modules/WhatsApp/routes.php');
    });
});
