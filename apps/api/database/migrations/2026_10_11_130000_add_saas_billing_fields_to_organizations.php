<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->string('plan')->default('basic'); // basic, pro, elite
            $table->string('billing_cycle')->default('monthly'); // monthly, annual
            $table->string('status')->default('active'); // active, past_due, suspended, trial
            $table->timestamp('expires_at')->nullable();
            $table->integer('max_whatsapp_lines')->default(1);
            $table->integer('extra_whatsapp_lines')->default(0);
            $table->integer('max_agents')->default(3);
            $table->integer('extra_agents')->default(0);
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->decimal('monthly_price_usd', 10, 2)->default(120.00);
            $table->text('license_token')->nullable();
            $table->jsonb('features')->nullable();
            $table->timestamp('last_payment_at')->nullable();
        });

        Schema::create('tenant_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount_usd', 10, 2);
            $table->string('concept');
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->string('payment_method')->default('transfer'); // transfer, cash, card, crypto
            $table->string('reference_code')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_invoices');

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropColumn([
                'plan',
                'billing_cycle',
                'status',
                'expires_at',
                'max_whatsapp_lines',
                'extra_whatsapp_lines',
                'max_agents',
                'extra_agents',
                'contact_name',
                'contact_phone',
                'contact_email',
                'monthly_price_usd',
                'license_token',
                'features',
                'last_payment_at',
            ]);
        });
    }
};
