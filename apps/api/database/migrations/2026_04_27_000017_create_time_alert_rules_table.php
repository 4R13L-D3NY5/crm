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
        if (!Schema::hasTable('time_alert_rules')) {
            Schema::create('time_alert_rules', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150);
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);

                // Minutos de espera máxima antes de disparar alerta
                $table->unsignedInteger('user_timeout_minutes')->default(20);
                $table->unsignedInteger('client_timeout_minutes')->default(120);

                // Toggles para habilitar monitoreo específico
                $table->boolean('notify_user_inactivity')->default(true);
                $table->boolean('notify_client_inactivity')->default(true);

                // Estados del lead a los que aplica la regla
                $table->boolean('apply_to_all_statuses')->default(true);
                $table->jsonb('custom_status_ids')->nullable();

                // Nivel de severidad y acción
                $table->string('severity', 30)->default('warning'); // info, warning, critical
                $table->string('action_type', 50)->default('visual_badge'); // visual_badge, notification, reassign

                $table->timestamps();

                $table->index(['organization_id', 'is_active']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_alert_rules');
    }
};
