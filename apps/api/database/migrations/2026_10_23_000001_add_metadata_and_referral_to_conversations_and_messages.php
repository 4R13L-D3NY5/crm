<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            if (!Schema::hasColumn('conversations', 'metadata')) {
                $table->json('metadata')->nullable()->after('feedback');
            }
            if (!Schema::hasColumn('conversations', 'origin_referral')) {
                $table->json('origin_referral')->nullable()->after('metadata');
            }
        });

        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'metadata')) {
                $table->json('metadata')->nullable()->after('original_body');
            }
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });

        Schema::table('conversations', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('conversations', 'origin_referral')) {
                $cols[] = 'origin_referral';
            }
            if (Schema::hasColumn('conversations', 'metadata')) {
                $cols[] = 'metadata';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
