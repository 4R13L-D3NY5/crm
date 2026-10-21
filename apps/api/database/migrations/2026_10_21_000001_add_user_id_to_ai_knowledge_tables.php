<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_knowledge_bases')) {
            Schema::table('ai_knowledge_bases', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_knowledge_bases', 'user_id')) {
                    $table->foreignUlid('user_id')->nullable()->after('organization_id')->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('ai_knowledge_chunks')) {
            Schema::table('ai_knowledge_chunks', function (Blueprint $table) {
                if (!Schema::hasColumn('ai_knowledge_chunks', 'user_id')) {
                    $table->foreignUlid('user_id')->nullable()->after('knowledge_base_id')->constrained('users')->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ai_knowledge_chunks')) {
            Schema::table('ai_knowledge_chunks', function (Blueprint $table) {
                if (Schema::hasColumn('ai_knowledge_chunks', 'user_id')) {
                    $table->dropForeign(['user_id']);
                    $table->dropColumn('user_id');
                }
            });
        }

        if (Schema::hasTable('ai_knowledge_bases')) {
            Schema::table('ai_knowledge_bases', function (Blueprint $table) {
                if (Schema::hasColumn('ai_knowledge_bases', 'user_id')) {
                    $table->dropForeign(['user_id']);
                    $table->dropColumn('user_id');
                }
            });
        }
    }
};
