<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_statuses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 30)->default('#10b981');
            $table->string('icon', 60)->nullable();
            $table->string('stage_type', 30)->default('in_progress'); // initial, in_progress, won, lost
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'sort_order']);
            $table->index(['organization_id', 'stage_type']);
        });

        Schema::create('custom_status_transitions', function (Blueprint $table) {
            $table->foreignUlid('from_status_id')->constrained('custom_statuses')->cascadeOnDelete();
            $table->foreignUlid('to_status_id')->constrained('custom_statuses')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['from_status_id', 'to_status_id']);
            $table->index('to_status_id');
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignUlid('custom_status_id')->nullable()->constrained('custom_statuses')->nullOnDelete();
            $table->index(['organization_id', 'custom_status_id']);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['custom_status_id']);
            $table->dropColumn('custom_status_id');
        });

        Schema::dropIfExists('custom_status_transitions');
        Schema::dropIfExists('custom_statuses');
    }
};
