<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tabla pivote: Horarios <-> Canales (WhatsApp y Redes Sociales)
        if (!Schema::hasTable('business_hours_channels')) {
            Schema::create('business_hours_channels', function (Blueprint $table) {
                $table->foreignUlid('business_hours_config_id')
                    ->constrained('business_hours_configs')
                    ->cascadeOnDelete();
                $table->foreignUlid('whatsapp_account_id')
                    ->constrained('whatsapp_accounts')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['business_hours_config_id', 'whatsapp_account_id'], 'bh_channels_primary');
            });
        }

        // 2. Tabla pivote: Horarios <-> Categorías (Opcional para enrutamiento por tema)
        if (!Schema::hasTable('business_hours_categories')) {
            Schema::create('business_hours_categories', function (Blueprint $table) {
                $table->foreignUlid('business_hours_config_id')
                    ->constrained('business_hours_configs')
                    ->cascadeOnDelete();
                $table->foreignUlid('category_id')
                    ->constrained('categories')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['business_hours_config_id', 'category_id'], 'bh_categories_primary');
            });
        }

        // 3. Tabla pivote: Horarios <-> Filas / Departamentos (Opcional)
        if (!Schema::hasTable('business_hours_queues')) {
            Schema::create('business_hours_queues', function (Blueprint $table) {
                $table->foreignUlid('business_hours_config_id')
                    ->constrained('business_hours_configs')
                    ->cascadeOnDelete();
                $table->foreignUlid('queue_id')
                    ->constrained('queues')
                    ->cascadeOnDelete();
                $table->timestamps();

                $table->primary(['business_hours_config_id', 'queue_id'], 'bh_queues_primary');
            });
        }

        // 4. BACKFILL AUTOMÁTICO DE DATOS EXISTENTES (Preservar la configuración actual)
        // Para cada horario laboral existente, vincular automáticamente todos los canales activos
        // y los operadores autorizados actuales, garantizando retrocompatibilidad al 100%.
        $now = now();
        $configs = DB::table('business_hours_configs')->get();

        foreach ($configs as $config) {
            // Canales de la organización
            $channels = DB::table('whatsapp_accounts')
                ->where('organization_id', $config->organization_id)
                ->get();

            foreach ($channels as $channel) {
                DB::table('business_hours_channels')->insertOrIgnore([
                    'business_hours_config_id' => $config->id,
                    'whatsapp_account_id' => $channel->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                // Operadores autorizados en este canal
                $assignedUsers = DB::table('whatsapp_account_users')
                    ->where('whatsapp_account_id', $channel->id)
                    ->pluck('user_id');

                foreach ($assignedUsers as $userId) {
                    DB::table('business_hours_users')->insertOrIgnore([
                        'business_hours_config_id' => $config->id,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }

            // Si business_hours_users quedó vacío, agregar los usuarios de la organización
            $hasUsers = DB::table('business_hours_users')
                ->where('business_hours_config_id', $config->id)
                ->exists();

            if (!$hasUsers && Schema::hasTable('organization_user')) {
                $orgUsers = DB::table('organization_user')
                    ->where('organization_id', $config->organization_id)
                    ->pluck('user_id');

                foreach ($orgUsers as $userId) {
                    DB::table('business_hours_users')->insertOrIgnore([
                        'business_hours_config_id' => $config->id,
                        'user_id' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('business_hours_queues');
        Schema::dropIfExists('business_hours_categories');
        Schema::dropIfExists('business_hours_channels');
    }
};
