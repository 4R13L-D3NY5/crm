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
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'media_url')) {
                $table->string('media_url')->nullable();
            }
            if (!Schema::hasColumn('messages', 'media_duration_seconds')) {
                $table->unsignedInteger('media_duration_seconds')->nullable();
            }
            if (!Schema::hasColumn('messages', 'transcription')) {
                $table->text('transcription')->nullable();
            }
            if (!Schema::hasColumn('messages', 'transcription_status')) {
                $table->string('transcription_status', 30)->default('none');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('messages', 'media_url')) $cols[] = 'media_url';
            if (Schema::hasColumn('messages', 'media_duration_seconds')) $cols[] = 'media_duration_seconds';
            if (Schema::hasColumn('messages', 'transcription')) $cols[] = 'transcription';
            if (Schema::hasColumn('messages', 'transcription_status')) $cols[] = 'transcription_status';

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
