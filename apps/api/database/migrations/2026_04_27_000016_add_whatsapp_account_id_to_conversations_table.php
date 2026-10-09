<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('conversations', 'whatsapp_account_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->foreignUlid('whatsapp_account_id')
                    ->nullable()
                    ->after('channel')
                    ->constrained('whatsapp_accounts')
                    ->nullOnDelete();

                $table->index(['organization_id', 'whatsapp_account_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('conversations', 'whatsapp_account_id')) {
            Schema::table('conversations', function (Blueprint $table) {
                $table->dropForeign(['whatsapp_account_id']);
                $table->dropColumn('whatsapp_account_id');
            });
        }
    }
};
