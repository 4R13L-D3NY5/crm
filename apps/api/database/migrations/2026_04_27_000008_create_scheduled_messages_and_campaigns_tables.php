<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Mensajes Programados diferidos
        if (!Schema::hasTable('scheduled_messages')) {
            Schema::create('scheduled_messages', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('contact_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignUlid('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->nullOnDelete();
                $table->string('recipient_phone', 50);
                $table->text('body');
                $table->string('media_url')->nullable();
                $table->timestamp('scheduled_at');
                $table->string('status', 20)->default('pending'); // pending, sent, failed, cancelled
                $table->timestamp('sent_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['organization_id', 'status', 'scheduled_at']);
            });
        }

        // 2. Campañas de Disparo Masivo
        if (!Schema::hasTable('campaigns')) {
            Schema::create('campaigns', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->nullOnDelete();
                $table->string('name');
                $table->string('status', 20)->default('draft'); // draft, processing, completed, cancelled
                $table->unsignedInteger('total_contacts')->default(0);
                $table->unsignedInteger('sent_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->unsignedSmallInteger('delay_seconds')->default(20);
                $table->text('message_template');
                $table->timestamp('scheduled_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['organization_id', 'status']);
            });
        }

        // 3. Destinatarios de la Campaña
        if (!Schema::hasTable('campaign_recipients')) {
            Schema::create('campaign_recipients', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('campaign_id')->constrained('campaigns')->cascadeOnDelete();
                $table->foreignUlid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->string('phone', 50);
                $table->string('name', 150)->nullable();
                $table->string('status', 20)->default('pending'); // pending, sent, failed
                $table->timestamp('sent_at')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamps();

                $table->index(['campaign_id', 'status']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('scheduled_messages');
    }
};
