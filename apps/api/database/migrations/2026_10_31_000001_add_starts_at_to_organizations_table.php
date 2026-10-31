<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'starts_at')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->timestamp('starts_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'starts_at')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('starts_at');
            });
        }
    }
};
