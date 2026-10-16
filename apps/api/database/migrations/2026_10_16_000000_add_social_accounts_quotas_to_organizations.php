<?php

use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'max_social_accounts')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->integer('max_social_accounts')->default(8)->after('max_whatsapp_lines');
                $table->integer('extra_social_accounts')->default(0)->after('extra_whatsapp_lines');
            });
        }

        // Actualizar cuotas según los nuevos planes aprobados:
        // Básico: 8 redes + 1 whatsapp (3 agentes)
        // Pro: 12 redes + 2 whatsapp (7 agentes)
        // Elite: 16 redes + 4 whatsapp (15 agentes)
        $organizations = Organization::all();
        foreach ($organizations as $org) {
            $plan = strtolower($org->plan ?? 'basic');

            if ($plan === 'elite' || $org->slug === 'unitepc') {
                $org->update([
                    'plan' => 'elite',
                    'max_whatsapp_lines' => 4 + ($org->extra_whatsapp_lines ?? 0),
                    'max_social_accounts' => 16 + ($org->extra_social_accounts ?? 0),
                    'max_agents' => max($org->max_agents ?? 15, 15 + ($org->extra_agents ?? 0)),
                ]);
            } elseif ($plan === 'pro') {
                $org->update([
                    'plan' => 'pro',
                    'max_whatsapp_lines' => 2 + ($org->extra_whatsapp_lines ?? 0),
                    'max_social_accounts' => 12 + ($org->extra_social_accounts ?? 0),
                    'max_agents' => max($org->max_agents ?? 7, 7 + ($org->extra_agents ?? 0)),
                ]);
            } else {
                $org->update([
                    'plan' => 'basic',
                    'max_whatsapp_lines' => 1 + ($org->extra_whatsapp_lines ?? 0),
                    'max_social_accounts' => 8 + ($org->extra_social_accounts ?? 0),
                    'max_agents' => max($org->max_agents ?? 3, 3 + ($org->extra_agents ?? 0)),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'max_social_accounts')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn(['max_social_accounts', 'extra_social_accounts']);
            });
        }
    }
};
