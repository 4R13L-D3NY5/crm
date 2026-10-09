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
        if (!Schema::hasTable('business_hours_configs')) {
            Schema::create('business_hours_configs', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->foreignUlid('organization_id')->constrained()->cascadeOnDelete();
                $table->string('name', 150)->default('Horario Laboral General');
                $table->boolean('is_active')->default(true);

                // Tipo de vigencia: 'immediate' (iniciar ya / continuo) o 'date_range' (rango de fechas programado)
                $table->string('validity_type', 30)->default('immediate');
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();

                // Zona horaria
                $table->string('timezone', 60)->default('America/La_Paz');

                // Configuración de días de la semana y horas (lunes a domingo)
                $table->jsonb('schedule_days');

                // Respuesta automática fuera de horario laboral
                $table->boolean('auto_reply_enabled')->default(true);
                $table->text('auto_reply_message');

                $table->timestamps();

                $table->unique('organization_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_hours_configs');
    }
};
