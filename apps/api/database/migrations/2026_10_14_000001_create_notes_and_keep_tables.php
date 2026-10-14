<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla de Notas
        if (!Schema::hasTable('notes')) {
            Schema::create('notes', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
                $table->string('title')->nullable();
                $table->text('content');
                $table->string('color', 30)->default('#1e293b');
                $table->boolean('is_pinned')->default(false);
                $table->boolean('is_archived')->default(false);
                $table->string('shortcut', 50)->nullable();
                $table->string('visibility', 20)->default('all'); // 'all', 'categories', 'private'
                $table->jsonb('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['organization_id', 'is_pinned', 'is_archived']);
                $table->index(['organization_id', 'shortcut']);
                $table->index(['organization_id', 'visibility']);
            });
        }

        // 2. Pivote Notas <-> Categorías
        if (!Schema::hasTable('note_categories')) {
            Schema::create('note_categories', function (Blueprint $table) {
                $table->foreignUlid('note_id')->constrained('notes')->cascadeOnDelete();
                $table->foreignUlid('category_id')->constrained('categories')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['note_id', 'category_id']);
                $table->index('category_id');
            });
        }

        // 3. Pivote Notas <-> Estados Personalizados
        if (!Schema::hasTable('note_custom_statuses')) {
            Schema::create('note_custom_statuses', function (Blueprint $table) {
                $table->foreignUlid('note_id')->constrained('notes')->cascadeOnDelete();
                $table->foreignUlid('custom_status_id')->constrained('custom_statuses')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['note_id', 'custom_status_id']);
                $table->index('custom_status_id');
            });
        }

        // 4. Pivote Notas <-> Etiquetas
        if (!Schema::hasTable('note_tags')) {
            Schema::create('note_tags', function (Blueprint $table) {
                $table->foreignUlid('note_id')->constrained('notes')->cascadeOnDelete();
                $table->foreignUlid('tag_id')->constrained('tags')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['note_id', 'tag_id']);
                $table->index('tag_id');
            });
        }

        // 5. Asignación de Categorías a Usuarios / Agentes
        if (!Schema::hasTable('user_categories')) {
            Schema::create('user_categories', function (Blueprint $table) {
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('category_id')->constrained('categories')->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['user_id', 'category_id']);
                $table->index(['organization_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_categories');
        Schema::dropIfExists('note_tags');
        Schema::dropIfExists('note_custom_statuses');
        Schema::dropIfExists('note_categories');
        Schema::dropIfExists('notes');
    }
};
