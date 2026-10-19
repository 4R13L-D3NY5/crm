<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Pipelines\Models\Pipeline;
use App\Modules\Pipelines\Models\PipelineStage;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CleanProductionSeeder extends Seeder
{
    /**
     * Siembra un entorno de producción limpio, sin datos demo de prueba
     * para que la empresa/organización pueda iniciar vinculando sus propios canales reales.
     */
    public function run(): void
    {
        // 1. Organización Principal Oficial
        $organization = Organization::query()->updateOrCreate(
            ['slug' => 'unitepc'],
            [
                'name' => 'UNITEPC',
                'plan' => 'elite',
                'max_agents' => 15,
                'max_whatsapp_lines' => 4,
                'max_social_accounts' => 16,
                'extra_whatsapp_lines' => 0,
                'extra_social_accounts' => 0,
                'extra_agents' => 0,
                'monthly_price_usd' => 390.00,
                'status' => 'active',
                'billing_cycle' => 'monthly',
            ],
        );

        // 2. Usuario Administrador Oficial de UNITEPC
        $unitepcAdmin = User::query()->updateOrCreate(
            ['email' => 'admin@unitepc.pro'],
            [
                'name' => 'Administrador UNITEPC',
                'password' => 'password',
            ]
        );

        $organization->users()->syncWithoutDetaching([
            $unitepcAdmin->getKey() => [
                'id' => (string) Str::ulid(),
                'role' => 'owner',
            ],
        ]);

        $unitepcAdmin->forceFill([
            'current_organization_id' => $organization->getKey(),
        ])->save();

        // 3. SuperAdmin Global XpertiFlow (Plataforma Master)
        User::query()->updateOrCreate(
            ['email' => 'admin@crm.local'],
            [
                'name' => 'SuperAdmin XpertiFlow',
                'password' => 'password',
            ]
        );

        // 3. Pipeline Oficial de Admisiones y Ventas
        $pipeline = Pipeline::query()->updateOrCreate(
            ['organization_id' => $organization->getKey(), 'name' => 'Admisiones & Inscripciones'],
            ['is_default' => true],
        );

        foreach ([
            'Nuevo Prospecto' => ['position' => 1, 'probability' => 10, 'color' => '#3B82F6'],
            'Contactado' => ['position' => 2, 'probability' => 30, 'color' => '#06B6D4'],
            'En Evaluación / Visita' => ['position' => 3, 'probability' => 60, 'color' => '#F59E0B'],
            'Inscrito' => ['position' => 4, 'probability' => 100, 'color' => '#10B981'],
            'Descartado' => ['position' => 5, 'probability' => 0, 'color' => '#EF4444'],
        ] as $name => $data) {
            PipelineStage::query()->updateOrCreate(
                ['pipeline_id' => $pipeline->getKey(), 'name' => $name],
                $data,
            );
        }

        // 4. Etiquetas Base Oficiales
        foreach ([
            'Postulante 2026' => 'postulante-2026',
            'Pregrado' => 'pregrado',
            'Posgrado' => 'posgrado',
            'WhatsApp' => 'whatsapp',
            'Prioridad Alta' => 'prioridad-alta',
        ] as $name => $slug) {
            Tag::query()->updateOrCreate(
                ['organization_id' => $organization->getKey(), 'slug' => $slug],
                ['name' => $name],
            );
        }

        // 5. Flujo Oficial de Estados Académicos de Lead
        if (!CustomStatus::where('organization_id', $organization->id)->exists()) {
            CustomStatus::create([
                'organization_id' => $organization->id,
                'name' => 'No Contactado',
                'slug' => 'no-contactado',
                'color' => '#3b82f6',
                'icon' => 'sym_r_mark_chat_unread',
                'stage_type' => 'initial',
                'is_default' => true,
                'sort_order' => 1,
                'is_active' => true,
            ]);

            CustomStatus::create([
                'organization_id' => $organization->id,
                'name' => 'En Contacto',
                'slug' => 'en-contacto',
                'color' => '#f59e0b',
                'icon' => 'sym_r_chat',
                'stage_type' => 'in_progress',
                'sort_order' => 2,
                'is_active' => true,
            ]);

            CustomStatus::create([
                'organization_id' => $organization->id,
                'name' => 'Interesado',
                'slug' => 'interesado',
                'color' => '#8b5cf6',
                'icon' => 'sym_r_thumb_up',
                'stage_type' => 'in_progress',
                'sort_order' => 3,
                'is_active' => true,
            ]);

            CustomStatus::create([
                'organization_id' => $organization->id,
                'name' => 'Inscrito',
                'slug' => 'inscrito',
                'color' => '#10b981',
                'icon' => 'sym_r_school',
                'stage_type' => 'won',
                'sort_order' => 4,
                'is_active' => true,
            ]);

            CustomStatus::create([
                'organization_id' => $organization->id,
                'name' => 'Abandono / Descartado',
                'slug' => 'abandono',
                'color' => '#ef4444',
                'icon' => 'sym_r_cancel',
                'stage_type' => 'lost',
                'sort_order' => 5,
                'is_active' => true,
            ]);
        }
    }
}
