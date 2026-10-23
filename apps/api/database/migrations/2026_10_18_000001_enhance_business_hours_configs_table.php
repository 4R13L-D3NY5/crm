<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('business_hours_configs')) {
            Schema::table('business_hours_configs', function (Blueprint $table) {
                // Drop unique constraint if exists
                try {
                    $table->dropUnique('business_hours_configs_organization_id_unique');
                } catch (\Throwable $e) {
                    // ignore if already dropped
                }
                if (!Schema::hasColumn('business_hours_configs', 'is_default')) {
                    $table->boolean('is_default')->default(false)->after('is_active');
                }
                if (!Schema::hasColumn('business_hours_configs', 'scope_type')) {
                    $table->string('scope_type', 20)->default('general')->after('name');
                }
            });

            DB::table('business_hours_configs')->update(['is_default' => true]);
        }

        if (Schema::hasTable('business_hours_users')) {
            Schema::dropIfExists('business_hours_users');
        }

        Schema::create('business_hours_users', function (Blueprint $table) {
            $table->foreignUlid('business_hours_config_id')
                ->constrained('business_hours_configs')
                ->cascadeOnDelete();
            $table->foreignUlid('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['business_hours_config_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours_users');

        Schema::table('business_hours_configs', function (Blueprint $table) {
            $table->dropColumn(['is_default', 'scope_type']);
            $table->unique('organization_id');
        });
    }
};
