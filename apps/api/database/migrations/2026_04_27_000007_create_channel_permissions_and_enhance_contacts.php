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
        // 1. Tabla de permisos de usuarios por Conexión / Canal (Asignación granular XF)
        if (!Schema::hasTable('whatsapp_account_users')) {
            Schema::create('whatsapp_account_users', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('whatsapp_account_id')->constrained('whatsapp_accounts')->cascadeOnDelete();
                $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
                $table->boolean('can_view')->default(true);
                $table->boolean('can_reply')->default(true);
                $table->timestamps();

                $table->unique(['whatsapp_account_id', 'user_id']);
            });
        }

        // 2. Extender tabla contacts con campos de segmentación Whaticket
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                if (!Schema::hasColumn('contacts', 'avatar_url')) {
                    $table->string('avatar_url')->nullable()->after('notes');
                }
                if (!Schema::hasColumn('contacts', 'custom_fields')) {
                    $table->jsonb('custom_fields')->nullable()->after('avatar_url');
                }
                if (!Schema::hasColumn('contacts', 'last_message_at')) {
                    $table->timestamp('last_message_at')->nullable()->after('custom_fields');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('contacts')) {
            Schema::table('contacts', function (Blueprint $table) {
                $table->dropColumn(['avatar_url', 'custom_fields', 'last_message_at']);
            });
        }

        Schema::dropIfExists('whatsapp_account_users');
    }
};
