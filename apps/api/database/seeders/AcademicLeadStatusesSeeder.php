<?php

namespace Database\Seeders;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;

class AcademicLeadStatusesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $org) {
            $this->command->info("Registrando estados académicos para organización: {$org->name} ({$org->id})");

            // Si ya tiene estados registrados, omitir o actualizar
            if (CustomStatus::where('organization_id', $org->id)->exists()) {
                $this->command->warn("La organización {$org->name} ya tiene estados configurados. Omitiendo creación duplicada.");
                continue;
            }

            // 1. No Contactado (Inicial, por defecto)
            $noContactado = CustomStatus::create([
                'organization_id' => $org->id,
                'name' => 'No Contactado',
                'slug' => 'no-contactado',
                'color' => '#3b82f6',
                'icon' => 'sym_r_mark_chat_unread',
                'stage_type' => 'initial',
                'is_default' => true,
                'sort_order' => 1,
                'is_active' => true,
            ]);

            // 2. Contactado (En Progreso - Requiere: No Contactado)
            $contactado = CustomStatus::create([
                'organization_id' => $org->id,
                'name' => 'Contactado',
                'slug' => 'contactado',
                'color' => '#06b6d4',
                'icon' => 'sym_r_forum',
                'stage_type' => 'in_progress',
                'is_default' => false,
                'sort_order' => 2,
                'is_active' => true,
            ]);
            $contactado->allowedPreviousStatuses()->sync([$noContactado->id]);

            // 3. Interesado / En Seguimiento (En Progreso - Requiere: Contactado)
            $interesado = CustomStatus::create([
                'organization_id' => $org->id,
                'name' => 'Interesado / En Seguimiento',
                'slug' => 'interesado-en-seguimiento',
                'color' => '#f59e0b',
                'icon' => 'sym_r_school',
                'stage_type' => 'in_progress',
                'is_default' => false,
                'sort_order' => 3,
                'is_active' => true,
            ]);
            $interesado->allowedPreviousStatuses()->sync([$contactado->id]);

            // 4. Inscrito (Ganado / Won - Requiere: Contactado o Interesado)
            $inscrito = CustomStatus::create([
                'organization_id' => $org->id,
                'name' => 'Inscrito',
                'slug' => 'inscrito',
                'color' => '#10b981',
                'icon' => 'sym_r_check_circle',
                'stage_type' => 'won',
                'is_default' => false,
                'sort_order' => 4,
                'is_active' => true,
            ]);
            $inscrito->allowedPreviousStatuses()->sync([$contactado->id, $interesado->id]);

            // 5. No Interesado / Descartado (Perdido / Lost - Requiere: No Contactado, Contactado o Interesado)
            $descartado = CustomStatus::create([
                'organization_id' => $org->id,
                'name' => 'No Interesado / Descartado',
                'slug' => 'no-interesado-descartado',
                'color' => '#ef4444',
                'icon' => 'sym_r_cancel',
                'stage_type' => 'lost',
                'is_default' => false,
                'sort_order' => 5,
                'is_active' => true,
            ]);
            $descartado->allowedPreviousStatuses()->sync([$noContactado->id, $contactado->id, $interesado->id]);

            // Asignar estado inicial a conversaciones existentes que no tengan estado asignado
            $updatedConversations = Conversation::where('organization_id', $org->id)
                ->whereNull('custom_status_id')
                ->update(['custom_status_id' => $noContactado->id]);

            $this->command->info("Estados sembrados exitosamente. {$updatedConversations} conversaciones asignadas al estado inicial 'No Contactado'.");
        }
    }
}
