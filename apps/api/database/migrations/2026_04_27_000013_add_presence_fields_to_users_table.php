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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'presence_status')) {
                $table->string('presence_status', 30)->default('offline');
            }
            if (!Schema::hasColumn('users', 'last_seen_at')) {
                $table->timestamp('last_seen_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('users', 'presence_status')) $cols[] = 'presence_status';
            if (Schema::hasColumn('users', 'last_seen_at')) $cols[] = 'last_seen_at';

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
