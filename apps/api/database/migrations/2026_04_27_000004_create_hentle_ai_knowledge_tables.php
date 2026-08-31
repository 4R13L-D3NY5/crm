<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_knowledge_bases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('provider', 50)->default('gemini'); // gemini, openai, local
            $table->string('embedding_model', 100)->default('text-embedding-004');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'is_active']);
        });

        Schema::create('ai_knowledge_chunks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('knowledge_base_id')->constrained('ai_knowledge_bases')->cascadeOnDelete();
            $table->string('title', 200)->nullable();
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['organization_id', 'knowledge_base_id']);
        });

        // En PostgreSQL con pgvector agregamos la columna de embedding vectorial (768 dims para Gemini, 1536 para OpenAI)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE ai_knowledge_chunks ADD COLUMN embedding vector(768);');
            DB::statement('CREATE INDEX ai_chunks_embedding_idx ON ai_knowledge_chunks USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100);');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_knowledge_chunks');
        Schema::dropIfExists('ai_knowledge_bases');
    }
};
