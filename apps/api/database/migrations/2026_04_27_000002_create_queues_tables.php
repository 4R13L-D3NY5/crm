<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('color', 20)->default('#25D366');
            $table->text('greeting_message')->nullable();
            $table->text('out_of_hours_message')->nullable();
            $table->unsignedInteger('order_index')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('chatbot_options')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organization_id', 'is_active']);
            $table->index(['organization_id', 'order_index']);
        });

        Schema::create('queue_users', function (Blueprint $table) {
            $table->foreignUlid('queue_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['queue_id', 'user_id']);
        });

        Schema::create('business_hours', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('queue_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0 = Domingo, 1 = Lunes, ..., 6 = Sábado
            $table->time('open_time_1')->nullable();
            $table->time('close_time_1')->nullable();
            $table->time('open_time_2')->nullable();
            $table->time('close_time_2')->nullable();
            $table->boolean('is_closed')->default(false);
            $table->timestamps();

            $table->index(['organization_id', 'day_of_week']);
        });

        Schema::create('quick_messages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('shortcut', 50);
            $table->text('message');
            $table->string('media_url')->nullable();
            $table->string('media_type', 30)->nullable();
            $table->boolean('is_general')->default(true);
            $table->timestamps();

            $table->index(['organization_id', 'shortcut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quick_messages');
        Schema::dropIfExists('business_hours');
        Schema::dropIfExists('queue_users');
        Schema::dropIfExists('queues');
    }
};
