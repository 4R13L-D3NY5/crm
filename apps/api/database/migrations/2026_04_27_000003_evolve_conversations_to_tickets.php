<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignUlid('queue_id')->nullable()->after('company_id')->constrained('queues')->nullOnDelete();
            $table->foreignUlid('assigned_to_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->unsignedInteger('unread_count')->default(0)->after('status');
            $table->boolean('is_group')->default(false)->after('unread_count');
            $table->timestamp('closed_at')->nullable()->after('last_message_at');
            $table->unsignedTinyInteger('rating')->nullable()->after('closed_at');
            $table->text('feedback')->nullable()->after('rating');

            $table->index(['organization_id', 'queue_id']);
            $table->index(['organization_id', 'assigned_to_user_id']);
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_internal')->default(false)->after('direction');
            $table->string('media_url')->nullable()->after('body');
            $table->string('media_type', 30)->nullable()->after('media_url');
            $table->foreignUlid('quoted_message_id')->nullable()->after('media_type')->constrained('messages')->nullOnDelete();
            $table->string('delivery_status', 20)->default('sent')->after('sent_at');

            $table->index(['organization_id', 'is_internal']);
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropForeign(['quoted_message_id']);
            $table->dropColumn(['is_internal', 'media_url', 'media_type', 'quoted_message_id', 'delivery_status']);
        });

        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['queue_id']);
            $table->dropForeign(['assigned_to_user_id']);
            $table->dropColumn(['queue_id', 'assigned_to_user_id', 'unread_count', 'is_group', 'closed_at', 'rating', 'feedback']);
        });
    }
};
