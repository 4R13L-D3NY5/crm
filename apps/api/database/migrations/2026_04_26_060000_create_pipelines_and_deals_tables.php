<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipelines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('pipeline_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('position');
            $table->unsignedTinyInteger('probability')->default(0);
            $table->string('color')->default('#C35F24');
            $table->timestamps();

            $table->index(['pipeline_id', 'position']);
        });

        Schema::create('deals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('pipeline_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('pipeline_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('contact_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUlid('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('open');
            $table->decimal('amount', 12, 2)->default(0);
            $table->unsignedTinyInteger('probability')->default(0);
            $table->date('expected_close_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'pipeline_id']);
            $table->index(['organization_id', 'pipeline_stage_id']);
            $table->index(['organization_id', 'status']);
        });

        Schema::create('deal_stage_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('deal_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('from_stage_id')->nullable()->references('id')->on('pipeline_stages')->nullOnDelete();
            $table->foreignUlid('to_stage_id')->references('id')->on('pipeline_stages')->cascadeOnDelete();
            $table->foreignUlid('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deal_stage_histories');
        Schema::dropIfExists('deals');
        Schema::dropIfExists('pipeline_stages');
        Schema::dropIfExists('pipelines');
    }
};
