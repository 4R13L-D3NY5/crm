<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->string('color', 30)->default('#10b981');
            $table->string('icon', 60)->nullable();
            $table->boolean('is_selectable')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['organization_id', 'parent_id']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('categories')->cascadeOnDelete();
        });

        Schema::create('conversation_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['conversation_id', 'category_id']);
            $table->index(['organization_id', 'conversation_id']);
            $table->index(['organization_id', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_categories');
        Schema::dropIfExists('categories');
    }
};
