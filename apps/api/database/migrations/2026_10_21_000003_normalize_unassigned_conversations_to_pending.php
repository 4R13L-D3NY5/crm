<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('conversations')
            ->whereNull('assigned_to_user_id')
            ->where('status', 'open')
            ->update(['status' => 'pending']);
    }

    public function down(): void
    {
        // No-op
    }
};
