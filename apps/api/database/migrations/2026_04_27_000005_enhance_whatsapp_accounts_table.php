<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->string('session_type', 30)->default('qr_baileys')->after('name');
            $table->string('status', 30)->default('DISCONNECTED')->after('session_type');
            $table->text('qrcode_raw')->nullable()->after('status');
            $table->foreignUlid('default_queue_id')->nullable()->after('qrcode_raw')->constrained('queues')->nullOnDelete();
            $table->boolean('is_default')->default(false)->after('default_queue_id');
            $table->timestamp('last_connected_at')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_accounts', function (Blueprint $table) {
            $table->dropForeign(['default_queue_id']);
            $table->dropColumn(['session_type', 'status', 'qrcode_raw', 'default_queue_id', 'is_default', 'last_connected_at']);
        });
    }
};
