<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notes') && !Schema::hasColumn('notes', 'image_url')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->text('image_url')->nullable()->after('shortcut');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notes') && Schema::hasColumn('notes', 'image_url')) {
            Schema::table('notes', function (Blueprint $table) {
                $table->dropColumn('image_url');
            });
        }
    }
};
