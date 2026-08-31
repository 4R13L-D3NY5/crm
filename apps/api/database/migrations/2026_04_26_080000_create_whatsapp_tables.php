<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone_number_id')->unique();
            $table->string('display_phone_number')->nullable();
            $table->string('business_account_id')->nullable();
            $table->string('verify_token');
            $table->text('access_token')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['organization_id', 'phone_number_id']);
        });

        Schema::create('whatsapp_webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('whatsapp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->string('processing_status')->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'created_at']);
            $table->index(['whatsapp_account_id', 'created_at']);
            $table->index(['processing_status', 'created_at']);
        });

        Schema::create('whatsapp_message_mappings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('whatsapp_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('provider_message_id')->unique();
            $table->string('direction')->default('inbound');
            $table->string('status')->default('received');
            $table->string('from_phone')->nullable();
            $table->string('to_phone_number_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'direction']);
            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_mappings');
        Schema::dropIfExists('whatsapp_webhook_events');
        Schema::dropIfExists('whatsapp_accounts');
    }
};
