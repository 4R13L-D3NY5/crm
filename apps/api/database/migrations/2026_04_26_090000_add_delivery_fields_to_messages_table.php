<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->string('message_status')->nullable()->after('message_type');
            $table->text('error_message')->nullable()->after('message_status');

            $table->index(['conversation_id', 'message_status']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['conversation_id', 'message_status']);
            $table->dropColumn(['message_status', 'error_message']);
        });
    }
};
