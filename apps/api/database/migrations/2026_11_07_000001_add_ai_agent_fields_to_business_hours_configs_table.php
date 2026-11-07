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
                if (! Schema::hasColumn('business_hours_configs', 'responder_type')) {
                    $table->string('responder_type', 30)->default('static_text')->after('auto_reply_mode');
                }
                if (! Schema::hasColumn('business_hours_configs', 'ai_confidence_threshold')) {
                    $table->decimal('ai_confidence_threshold', 3, 2)->default(0.80)->after('responder_type');
                }
                if (! Schema::hasColumn('business_hours_configs', 'ai_escalation_enabled')) {
                    $table->boolean('ai_escalation_enabled')->default(true)->after('ai_confidence_threshold');
                }
                if (! Schema::hasColumn('business_hours_configs', 'ai_escalation_phone')) {
                    $table->string('ai_escalation_phone', 50)->nullable()->after('ai_escalation_enabled');
                }
                if (! Schema::hasColumn('business_hours_configs', 'ai_fallback_message')) {
                    $table->text('ai_fallback_message')->nullable()->after('ai_escalation_phone');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('business_hours_configs')) {
            Schema::table('business_hours_configs', function (Blueprint $table) {
                $cols = [];
                foreach ([
                    'responder_type',
                    'ai_confidence_threshold',
                    'ai_escalation_enabled',
                    'ai_escalation_phone',
                    'ai_fallback_message',
                ] as $col) {
                    if (Schema::hasColumn('business_hours_configs', $col)) {
                        $cols[] = $col;
                    }
                }
                if (! empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};
