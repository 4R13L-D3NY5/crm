<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('whatsapp_accounts', 'daily_limit')) {
            Schema::table('whatsapp_accounts', function (Blueprint $table) {
                $table->unsignedInteger('daily_limit')->default(100)->nullable()->after('session_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('whatsapp_accounts', 'daily_limit')) {
            Schema::table('whatsapp_accounts', function (Blueprint $table) {
                $table->dropColumn('daily_limit');
            });
        }
    }
};
