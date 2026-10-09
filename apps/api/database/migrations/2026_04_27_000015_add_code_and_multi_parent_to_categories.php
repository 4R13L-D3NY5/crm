<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar columna de código opcional a las categorías
        Schema::table('categories', function (Blueprint $table) {
            $table->string('code', 50)->nullable()->after('slug');
        });

        // 2. Crear tabla pivote para soportar múltiples padres por categoría (árbol tipo Grafo/DAG)
        Schema::create('category_parents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('category_id')->constrained('categories')->cascadeOnDelete();
            $table->foreignUlid('parent_id')->constrained('categories')->cascadeOnDelete();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['category_id', 'parent_id']);
            $table->index(['organization_id', 'parent_id']);
            $table->index(['organization_id', 'category_id']);
        });

        // 3. Migrar relaciones de parent_id existentes hacia la tabla category_parents
        $existing = DB::table('categories')->whereNotNull('parent_id')->get();
        foreach ($existing as $item) {
            DB::table('category_parents')->insert([
                'id' => (string) Str::ulid(),
                'organization_id' => $item->organization_id,
                'category_id' => $item->id,
                'parent_id' => $item->parent_id,
                'sort_order' => $item->sort_order ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_parents');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('code');
        });
    }
};
