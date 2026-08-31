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
        // 1. Configuraciones generales del Workspace por Organización
        if (!Schema::hasTable('workspace_settings')) {
            Schema::create('workspace_settings', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->string('default_language')->default('es');
                $table->string('timezone')->default('America/La_Paz');
                $table->boolean('hide_contact_data')->default(false);
                $table->boolean('enforce_2fa')->default(false);
                $table->integer('sla_timeout_minutes')->default(10);
                $table->jsonb('custom_options')->nullable();
                $table->timestamps();

                $table->unique('organization_id');
            });
        }

        // 2. Agregar color_hex a tabla tags existente
        if (Schema::hasTable('tags') && !Schema::hasColumn('tags', 'color_hex')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->string('color_hex', 10)->nullable()->default('#00a884')->after('slug');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('tags') && Schema::hasColumn('tags', 'color_hex')) {
            Schema::table('tags', function (Blueprint $table) {
                $table->dropColumn('color_hex');
            });
        }

        Schema::dropIfExists('workspace_settings');
    }
};
