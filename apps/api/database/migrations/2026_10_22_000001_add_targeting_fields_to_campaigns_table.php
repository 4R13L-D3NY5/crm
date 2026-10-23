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
        Schema::table('campaigns', function (Blueprint $table) {
            $table->string('target_type', 30)->default('all')->after('status');
            $table->string('target_summary', 255)->nullable()->after('target_type');
            $table->json('target_criteria')->nullable()->after('target_summary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['target_type', 'target_summary', 'target_criteria']);
        });
    }
};
