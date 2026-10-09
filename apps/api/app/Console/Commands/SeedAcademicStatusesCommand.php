<?php

namespace App\Console\Commands;

use App\Modules\Conversations\Models\Conversation;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Console\Command;

class SeedAcademicStatusesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:seed-academic-statuses';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Siembra el flujo simplificado de estados académicos de lead para todas las organizaciones';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $organizations = Organization::all();

        if ($organizations->isEmpty()) {
            $this->warn('No se encontraron organizaciones registradas.');
            return self::FAILURE;
        }

        foreach ($organizations as $org) {
            $this->info("Procesando organización: {$org->name} [{$org->id}]");

            // Si ya tiene estados registrados, limpiamos transiciones previas o reutilizamos
            $existingCount = CustomStatus::where('organization_id', $org->id)->count();
            if ($existingCount > 0) {
                $this->warn("La organización ya cuenta con {$existingCount} estados. Omitiendo duplicación.");
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

            // Asignar estado inicial a todas las conversaciones de la organización que no tengan estado
            $updated = Conversation::where('organization_id', $org->id)
                ->whereNull('custom_status_id')
                ->update(['custom_status_id' => $noContactado->id]);

            $this->info("✓ 5 estados y sus reglas creados con éxito. Se asignó 'No Contactado' a {$updated} conversaciones.");
        }

        $this->info('Flujo académico registrado exitosamente en la base de datos.');
        return self::SUCCESS;
    }
}
