<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->ulid('custom_status_id')->nullable()->after('status');
            $table->foreign('custom_status_id')->references('id')->on('custom_statuses')->nullOnDelete();
            $table->index('custom_status_id');
        });

        Schema::create('contact_categories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('organization_id')->index();
            $table->ulid('contact_id')->index();
            $table->ulid('category_id')->index();
            $table->ulid('assigned_by_user_id')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('contact_id')->references('id')->on('contacts')->cascadeOnDelete();
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();

            $table->unique(['contact_id', 'category_id']);
        });

        // Sincronizar estado inicial: para los contactos con conversaciones, adoptar el custom_status_id de su conversación
        DB::statement("
            UPDATE contacts c
            SET custom_status_id = (
                SELECT conv.custom_status_id
                FROM conversations conv
                WHERE conv.contact_id = c.id
                  AND conv.custom_status_id IS NOT NULL
                ORDER BY conv.last_message_at DESC NULLS LAST, conv.created_at DESC
                LIMIT 1
            )
            WHERE c.custom_status_id IS NULL
        ");

        // Para los contactos que aún no tienen custom_status_id, asignar el estado por defecto de su organización
        DB::statement("
            UPDATE contacts c
            SET custom_status_id = (
                SELECT cs.id
                FROM custom_statuses cs
                WHERE cs.organization_id = c.organization_id
                  AND cs.is_default = true
                LIMIT 1
            )
            WHERE c.custom_status_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_categories');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropForeign(['custom_status_id']);
            $table->dropColumn('custom_status_id');
        });
    }
};
