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
        if (!Schema::hasTable('social_comments')) {
            Schema::create('social_comments', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignUlid('whatsapp_account_id')->nullable()->constrained('whatsapp_accounts')->nullOnDelete();
                $table->foreignUlid('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
                $table->foreignUlid('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
                $table->string('platform', 30)->default('facebook'); // facebook, instagram, tiktok
                $table->string('post_id', 100);
                $table->string('comment_id', 100);
                $table->string('author_name', 150);
                $table->string('author_id', 100)->nullable();
                $table->text('comment_text');
                $table->text('reply_text')->nullable();
                $table->boolean('is_replied')->default(false);
                $table->boolean('ticket_created')->default(false);
                $table->timestamps();

                $table->unique(['platform', 'comment_id']);
                $table->index(['organization_id', 'platform', 'post_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('social_comments');
    }
};
