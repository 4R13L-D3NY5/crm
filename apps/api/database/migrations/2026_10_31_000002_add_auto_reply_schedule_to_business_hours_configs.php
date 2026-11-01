<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_hours_configs')) {
            Schema::table('business_hours_configs', function (Blueprint $table) {
                if (! Schema::hasColumn('business_hours_configs', 'auto_reply_mode')) {
                    $table->string('auto_reply_mode', 30)->default('outside_hours')->after('auto_reply_enabled');
                }
                if (! Schema::hasColumn('business_hours_configs', 'auto_reply_schedule')) {
                    $table->json('auto_reply_schedule')->nullable()->after('auto_reply_mode');
                }
                if (! Schema::hasColumn('business_hours_configs', 'auto_reply_cooldown_minutes')) {
                    $table->integer('auto_reply_cooldown_minutes')->default(1440)->after('auto_reply_schedule');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_hours_configs')) {
            Schema::table('business_hours_configs', function (Blueprint $table) {
                $cols = [];
                if (Schema::hasColumn('business_hours_configs', 'auto_reply_mode')) {
                    $cols[] = 'auto_reply_mode';
                }
                if (Schema::hasColumn('business_hours_configs', 'auto_reply_schedule')) {
                    $cols[] = 'auto_reply_schedule';
                }
                if (Schema::hasColumn('business_hours_configs', 'auto_reply_cooldown_minutes')) {
                    $cols[] = 'auto_reply_cooldown_minutes';
                }
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
