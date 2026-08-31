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
        if (!Schema::hasTable('bot_flows')) {
            Schema::create('bot_flows', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('queue_id')->nullable()->constrained('queues')->nullOnDelete();
                $table->string('name', 150);
                $table->string('trigger_keyword', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->text('greeting_message');
                $table->json('options'); // Array de opciones [{ option_number: "1", label: "Admisiones", queue_id: "...", reply_text: "..." }]
                $table->boolean('handoff_to_ai')->default(true);
                $table->text('fallback_message')->nullable();
                $table->timestamps();

                $table->index(['organization_id', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bot_flows');
    }
};
