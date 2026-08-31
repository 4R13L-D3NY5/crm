<?php

namespace App\Providers;

use App\Modules\AiAgent\Services\AiProvider;
use App\Modules\AiAgent\Services\FakeAiProvider;
use App\Modules\Audit\Policies\AuditLogPolicy;
use App\Modules\Automations\Models\AutomationRule;
use App\Modules\Automations\Policies\AutomationRulePolicy;
use App\Modules\Deals\Models\Deal;
use App\Modules\Deals\Policies\DealPolicy;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Policies\ContactPolicy;
use App\Modules\Companies\Models\Company;
use App\Modules\Companies\Policies\CompanyPolicy;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Policies\ConversationPolicy;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use App\Modules\Pipelines\Policies\PipelinePolicy;
use App\Modules\Pipelines\Policies\PipelineStagePolicy;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Policies\OrganizationPolicy;
use App\Modules\WhatsApp\Services\MetaWhatsAppClient;
use App\Modules\WhatsApp\Services\WhatsAppClient;
use App\Shared\Models\AuditLog;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AiProvider::class, FakeAiProvider::class);
        $this->app->bind(WhatsAppClient::class, MetaWhatsAppClient::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            return Limit::perMinute(5)->by(
                strtolower((string) $request->input('email')).'|'.$request->ip()
            );
        });

        RateLimiter::for('whatsapp-webhook', function (Request $request): Limit {
            return Limit::perMinute(120)->by(
                data_get($request->all(), 'entry.0.changes.0.value.metadata.phone_number_id', $request->ip())
            );
        });

        Gate::policy(AutomationRule::class, AutomationRulePolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Contact::class, ContactPolicy::class);
        Gate::policy(Conversation::class, ConversationPolicy::class);
        Gate::policy(Deal::class, DealPolicy::class);
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Pipeline::class, PipelinePolicy::class);
        Gate::policy(PipelineStage::class, PipelineStagePolicy::class);
    }
}
