<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Laravel already provisions failed_jobs in the base jobs migration.
    }

    public function down(): void
    {
        // No-op to preserve migration history in existing environments.
    }
};
