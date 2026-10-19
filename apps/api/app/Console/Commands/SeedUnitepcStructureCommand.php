<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Parameters\Models\BusinessHoursConfig;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Queues\Models\Queue;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedUnitepcStructureCommand extends Command
{
    protected $signature = 'crm:seed-unitepc-structure {--org= : Slug o ID de la organización objetivo (opcional)}';
    protected $description = 'Siembra la estructura oficial de UNITEPC: Estados de Admisiones sin Preinscrito, Códigos Oficiales de Carreras/Sedes, 5 Operadores Regionales y Horarios Laborales Parametrizables';

    public function handle(): int
    {
        $this->info('=== Iniciando Configuración de Estructura Oficial UNITEPC ===');

        $orgOption = $this->option('org');

        if ($orgOption) {
            $organizations = Organization::where('slug', $orgOption)
                ->orWhere('id', $orgOption)
                ->orWhere('name', 'like', "%{$orgOption}%")
                ->get();
        } else {
            $unitepcOrgs = Organization::where('slug', 'unitepc')
                ->orWhere('name', 'like', '%UNITEPC%')
                ->get();

            $organizations = $unitepcOrgs->isNotEmpty() ? $unitepcOrgs : Organization::all();
        }

        if ($organizations->isEmpty()) {
            $this->error('No se encontró ninguna organización para configurar.');
            return self::FAILURE;
        }

        foreach ($organizations as $org) {
            $this->info("Configurando datos para: {$org->name} [{$org->id}]");

            $this->seedCustomStatusesOption1($org);
            $this->seedUnitepcCategories($org);
            $this->seedRegionalOperatorsAndQueues($org);
            $this->seedBusinessHours($org);
        }

        $this->info('=== Configuración de UNITEPC completada con éxito ===');
        return self::SUCCESS;
    }

    private function seedCustomStatusesOption1(Organization $org): void
    {
        $this->line("-> Sembrando Estados de Embudo de Admisiones (Sin Preinscrito) para {$org->name}...");

        // 1. Limpiar estados antiguos de conversaciones
        $oldStatusIds = CustomStatus::where('organization_id', $org->id)->pluck('id')->all();
        if (!empty($oldStatusIds)) {
            DB::table('custom_status_transitions')
                ->whereIn('from_status_id', $oldStatusIds)
                ->orWhereIn('to_status_id', $oldStatusIds)
                ->delete();

            Conversation::where('organization_id', $org->id)
                ->whereIn('custom_status_id', $oldStatusIds)
                ->update(['custom_status_id' => null]);

            CustomStatus::where('organization_id', $org->id)->delete();
        }

        // Crear los 6 estados (eliminando Preinscrito según requerimiento)
        $st1 = CustomStatus::create([
            'organization_id' => $org->id,
            'name' => 'Nuevo Prospecto',
            'slug' => 'nuevo-prospecto',
            'color' => '#10b981',
            'icon' => 'sym_r_person_add',
            'stage_type' => 'initial',
            'is_default' => true,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $st2 = CustomStatus::create([
            'organization_id' => $org->id,
            'name' => 'Contactado / Info Enviada',
            'slug' => 'contactado-info-enviada',
            'color' => '#0284c7',
            'icon' => 'sym_r_mark_chat_read',
            'stage_type' => 'in_progress',
            'is_default' => false,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $st2->allowedPreviousStatuses()->sync([$st1->id]);

        $st3 = CustomStatus::create([
            'organization_id' => $org->id,
            'name' => 'Interesado Calificado',
            'slug' => 'interesado-calificado',
            'color' => '#eab308',
            'icon' => 'sym_r_star',
            'stage_type' => 'in_progress',
            'is_default' => false,
            'sort_order' => 3,
            'is_active' => true,
        ]);
        $st3->allowedPreviousStatuses()->sync([$st2->id]);

        $st4 = CustomStatus::create([
            'organization_id' => $org->id,
            'name' => 'Visita a Sede Agendada',
            'slug' => 'visita-sede-agendada',
            'color' => '#8b5cf6',
            'icon' => 'sym_r_calendar_month',
            'stage_type' => 'in_progress',
            'is_default' => false,
            'sort_order' => 4,
            'is_active' => true,
        ]);
        $st4->allowedPreviousStatuses()->sync([$st2->id, $st3->id]);

        // Matriculado (Ganado) - Se conecta directamente desde Interesado Calificado y Visita Agendada
        $st5 = CustomStatus::create([
            'organization_id' => $org->id,
            'name' => 'Matriculado (Ganado)',
            'slug' => 'matriculado-ganado',
            'color' => '#059669',
            'icon' => 'sym_r_school',
            'stage_type' => 'won',
            'is_default' => false,
            'sort_order' => 5,
            'is_active' => true,
        ]);
        $st5->allowedPreviousStatuses()->sync([$st3->id, $st4->id]);

        $st6 = CustomStatus::create([
            'organization_id' => $org->id,
            'name' => 'Perdido / Sin Interés',
            'slug' => 'perdido-sin-interes',
            'color' => '#64748b',
            'icon' => 'sym_r_cancel',
            'stage_type' => 'lost',
            'is_default' => false,
            'sort_order' => 6,
            'is_active' => true,
        ]);

        // Asignar el estado predeterminado a las conversaciones existentes
        Conversation::where('organization_id', $org->id)
            ->whereNull('custom_status_id')
            ->update(['custom_status_id' => $st1->id]);

        $this->info("   -> 6 Estados del Embudo configurados correctamente (Preinscrito eliminado).");
    }

    private function seedUnitepcCategories(Organization $org): void
    {
        $this->line("-> Sembrando Árbol de Categorías con Códigos Oficiales UNITEPC para {$org->name}...");

        // Limpiar categorías previas
        $catIds = Category::where('organization_id', $org->id)->pluck('id')->all();
        if (!empty($catIds)) {
            DB::table('conversation_categories')->whereIn('category_id', $catIds)->delete();
            DB::table('contact_categories')->whereIn('category_id', $catIds)->delete();
            DB::table('user_categories')->whereIn('category_id', $catIds)->delete();
            DB::table('note_categories')->whereIn('category_id', $catIds)->delete();
            DB::table('category_parents')->whereIn('category_id', $catIds)->orWhereIn('parent_id', $catIds)->delete();
            Category::where('organization_id', $org->id)->delete();
        }

        $sortIndex = 1;

        $addCat = function (string $name, ?string $parentId, ?string $code, string $color, ?string $icon = null, bool $isSelectable = true) use ($org, &$sortIndex): Category {
            $cat = Category::create([
                'organization_id' => $org->id,
                'parent_id' => $parentId,
                'name' => $name,
                'code' => $code ? strtoupper(trim($code)) : null,
                'slug' => Str::slug($name) . '-' . substr(Str::ulid(), -4),
                'color' => $color,
                'icon' => $icon ?: 'sym_r_label',
                'is_selectable' => $isSelectable,
                'sort_order' => $sortIndex++,
            ]);

            if ($parentId) {
                DB::table('category_parents')->insert([
                    'id' => (string) Str::ulid(),
                    'organization_id' => $org->id,
                    'category_id' => $cat->id,
                    'parent_id' => $parentId,
                    'sort_order' => $cat->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $cat;
        };

        // Diccionario de códigos oficiales de carreras extraídos del sistema UNITEPC
        $carreraCodeMap = [
            'Medicina' => 'CARMED',
            'Licenciatura en Medicina' => 'CARMED',
            'Odontología' => 'CARODO',
            'Licenciatura en Odontología' => 'CARODO',
            'Enfermería' => 'CARENL',
            'Licenciatura en Enfermería' => 'CARENL',
            'Medicina Veterinaria y Zootecnia' => 'CARVET',
            'Licenciatura en Medicina Veterinaria y Zootecnia' => 'CARVET',
            'Fisioterapia y Kinesiología' => 'CARFIS',
            'Licenciatura en Fisioterapia y Kinesiología' => 'CARFIS',
            'Bioquímica y Farmacia' => 'CARBYF',
            'Licenciatura en Bioquímica y Farmacia' => 'CARBYF',
            'Fonoaudiología' => 'CARFON',
            'Licenciatura en Fonoaudiología' => 'CARFON',
            'Nutrición y Dietética' => 'CARNYD',
            'Licenciatura en Nutrición y Dietética' => 'CARNYD',
            'Tec. Superior Prótesis Dental' => 'CARPRO',
            'Prótesis Dental' => 'CARPRO',
            'Ingeniería de Sonido' => 'CARSON',
            'Licenciatura en Ingeniería de Sonido' => 'CARSON',
            'Ingeniería de Sistemas' => 'CARSIS',
            'Licenciatura en Ingeniería de Sistemas' => 'CARSIS',
            'Ingeniería Electrónica' => 'CARELE',
            'Licenciatura en Ingeniería Electrónica' => 'CARELE',
            'Ingeniería Biomédica' => 'CARIBI',
            'Licenciatura en Ingeniería Biomédica' => 'CARIBI',
            'Contaduría Pública' => 'CARCPU',
            'Licenciatura en Contaduría Pública' => 'CARCPU',
            'Contaduría Pública (Complementaria)' => 'CARCCP',
            'Complementaria Contaduría Pública' => 'CARCCP',
            'Economía' => 'CARECO',
            'Licenciatura en Economía' => 'CARECO',
            'Administración de Empresas' => 'CARADM',
            'Licenciatura en Administración de Empresas' => 'CARADM',
            'Administración de Empresas (Complementaria)' => 'CARCAD',
            'Complementaria en Administración de Empresas' => 'CARCAD',
            'Ingeniería Comercial' => 'CARICO',
            'Licenciatura en Ingeniería Comercial' => 'CARICO',
            'Ingeniería Comercial (Complementaria)' => 'CARCIC',
            'Complementaria Ingeniería Comercial' => 'CARCIC',
            'Comunicación Social' => 'CARCSO',
            'Licenciatura en Comunicación Social' => 'CARCSO',
            'Derecho' => 'CARDER',
            'Licenciatura en Derecho' => 'CARDER',
            'Artes y Escultura' => 'CARAYE',
            'Arte y Escultura' => 'CARAYE',
            'Licenciatura en Arte y Escultura' => 'CARAYE',
            'Cinematografía' => 'CARCNE',
            'Licenciatura en Cinematografía' => 'CARCNE',
        ];

        // ==========================================
        // 1. SEDES (PREGRADO) - 9 SEDES OFICIALES
        // ==========================================
        $rootSedes = $addCat('Nuestras Sedes (Pregrado)', null, 'SEDES', '#01c9c5', 'sym_r_domain', false);

        $sedesData = [
            [
                'name' => 'Sede Cochabamba (Central)',
                'code' => 'CBA',
                'color' => '#0d9488',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'CBA-SALUD',
                        'carreras' => [
                            'Licenciatura en Medicina',
                            'Licenciatura en Odontología',
                            'Licenciatura en Enfermería',
                            'Licenciatura en Medicina Veterinaria y Zootecnia',
                            'Licenciatura en Fisioterapia y Kinesiología',
                            'Licenciatura en Bioquímica y Farmacia',
                            'Licenciatura en Fonoaudiología',
                            'Licenciatura en Nutrición y Dietética',
                            'Prótesis Dental',
                        ],
                    ],
                    [
                        'name' => 'Facultad de Ciencias de la Tecnología',
                        'code' => 'CBA-TEC',
                        'carreras' => [
                            'Licenciatura en Ingeniería de Sonido',
                            'Licenciatura en Ingeniería de Sistemas',
                            'Licenciatura en Ingeniería Electrónica',
                            'Licenciatura en Ingeniería Biomédica',
                        ],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Financieras',
                        'code' => 'CBA-ECON',
                        'carreras' => [
                            'Licenciatura en Contaduría Pública',
                            'Complementaria Contaduría Pública',
                            'Licenciatura en Economía',
                            'Licenciatura en Administración de Empresas',
                            'Complementaria en Administración de Empresas',
                            'Licenciatura en Ingeniería Comercial',
                            'Complementaria Ingeniería Comercial',
                        ],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Sociales y Jurídicas',
                        'code' => 'CBA-SOC',
                        'carreras' => [
                            'Licenciatura en Comunicación Social',
                            'Licenciatura en Derecho',
                            'Licenciatura en Arte y Escultura',
                            'Licenciatura en Cinematografía',
                        ],
                    ],
                ],
            ],
            [
                'name' => 'Sede La Paz',
                'code' => 'LPZ',
                'color' => '#2563eb',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'LPZ-SALUD',
                        'carreras' => ['Licenciatura en Medicina', 'Licenciatura en Odontología', 'Licenciatura en Enfermería'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias de la Tecnología',
                        'code' => 'LPZ-TEC',
                        'carreras' => ['Licenciatura en Ingeniería de Sonido', 'Licenciatura en Ingeniería de Sistemas', 'Licenciatura en Ingeniería Electrónica'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Financieras',
                        'code' => 'LPZ-ECON',
                        'carreras' => ['Licenciatura en Contaduría Pública', 'Complementaria Contaduría Pública', 'Licenciatura en Economía', 'Licenciatura en Administración de Empresas', 'Complementaria en Administración de Empresas', 'Licenciatura en Ingeniería Comercial', 'Complementaria Ingeniería Comercial'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Sociales y Jurídicas',
                        'code' => 'LPZ-SOC',
                        'carreras' => ['Licenciatura en Comunicación Social', 'Licenciatura en Derecho', 'Licenciatura en Cinematografía'],
                    ],
                ],
            ],
            [
                'name' => 'Sede El Alto',
                'code' => 'EAL',
                'color' => '#4f46e5',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'EAL-SALUD',
                        'carreras' => ['Licenciatura en Medicina', 'Licenciatura en Odontología', 'Licenciatura en Enfermería', 'Licenciatura en Fisioterapia y Kinesiología', 'Licenciatura en Bioquímica y Farmacia', 'Licenciatura en Fonoaudiología', 'Licenciatura en Nutrición y Dietética', 'Prótesis Dental'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias de la Tecnología',
                        'code' => 'EAL-TEC',
                        'carreras' => ['Licenciatura en Ingeniería de Sonido', 'Licenciatura en Ingeniería de Sistemas', 'Licenciatura en Ingeniería Electrónica', 'Licenciatura en Ingeniería Biomédica'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Financieras',
                        'code' => 'EAL-ECON',
                        'carreras' => ['Licenciatura en Contaduría Pública', 'Complementaria Contaduría Pública', 'Licenciatura en Administración de Empresas', 'Complementaria en Administración de Empresas', 'Complementaria Ingeniería Comercial'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Sociales y Jurídicas',
                        'code' => 'EAL-SOC',
                        'carreras' => ['Licenciatura en Comunicación Social', 'Licenciatura en Derecho', 'Licenciatura en Arte y Escultura'],
                    ],
                ],
            ],
            [
                'name' => 'Sede Santa Cruz',
                'code' => 'STC',
                'color' => '#059669',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'STC-SALUD',
                        'carreras' => ['Licenciatura en Medicina', 'Licenciatura en Odontología', 'Licenciatura en Enfermería', 'Licenciatura en Medicina Veterinaria y Zootecnia', 'Licenciatura en Fisioterapia y Kinesiología', 'Licenciatura en Bioquímica y Farmacia', 'Licenciatura en Fonoaudiología'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias de la Tecnología',
                        'code' => 'STC-TEC',
                        'carreras' => ['Licenciatura en Ingeniería de Sonido'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Financieras',
                        'code' => 'STC-ECON',
                        'carreras' => ['Licenciatura en Contaduría Pública', 'Licenciatura en Administración de Empresas', 'Licenciatura en Ingeniería Comercial'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Sociales y Jurídicas',
                        'code' => 'STC-SOC',
                        'carreras' => ['Licenciatura en Cinematografía'],
                    ],
                ],
            ],
            [
                'name' => 'Sede Ivirgarzama (Trópico)',
                'code' => 'IVI',
                'color' => '#16a34a',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'IVI-SALUD',
                        'carreras' => ['Licenciatura en Enfermería', 'Licenciatura en Medicina Veterinaria y Zootecnia'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias de la Ingeniería',
                        'code' => 'IVI-ING',
                        'carreras' => ['Licenciatura en Ingeniería de Sonido'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Administrativas',
                        'code' => 'IVI-ECON',
                        'carreras' => ['Licenciatura en Contaduría Pública', 'Licenciatura en Administración de Empresas'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Sociales y Jurídicas',
                        'code' => 'IVI-SOC',
                        'carreras' => ['Licenciatura en Derecho'],
                    ],
                ],
            ],
            [
                'name' => 'Sede Cobija (Pando)',
                'code' => 'CBJ',
                'color' => '#d97706',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'CBJ-SALUD',
                        'carreras' => ['Licenciatura en Medicina', 'Licenciatura en Odontología', 'Licenciatura en Enfermería', 'Licenciatura en Medicina Veterinaria y Zootecnia', 'Licenciatura en Bioquímica y Farmacia', 'Licenciatura en Fonoaudiología', 'Licenciatura en Nutrición y Dietética'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias de la Tecnología',
                        'code' => 'CBJ-TEC',
                        'carreras' => ['Licenciatura en Ingeniería de Sistemas', 'Licenciatura en Ingeniería Biomédica'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Financieras',
                        'code' => 'CBJ-ECON',
                        'carreras' => ['Licenciatura en Ingeniería Comercial', 'Complementaria Contaduría Pública', 'Complementaria en Administración de Empresas', 'Complementaria Ingeniería Comercial'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Sociales y Jurídicas',
                        'code' => 'CBJ-SOC',
                        'carreras' => ['Licenciatura en Derecho', 'Licenciatura en Arte y Escultura'],
                    ],
                ],
            ],
            [
                'name' => 'Sede Puerto Quijarro',
                'code' => 'PTO',
                'color' => '#ea580c',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'PTO-SALUD',
                        'carreras' => ['Licenciatura en Medicina', 'Licenciatura en Odontología', 'Licenciatura en Enfermería', 'Licenciatura en Bioquímica y Farmacia', 'Licenciatura en Fonoaudiología', 'Licenciatura en Nutrición y Dietética'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias de la Tecnología',
                        'code' => 'PTO-TEC',
                        'carreras' => ['Licenciatura en Ingeniería de Sistemas', 'Licenciatura en Ingeniería Biomédica'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Financieras',
                        'code' => 'PTO-ECON',
                        'carreras' => ['Licenciatura en Ingeniería Comercial', 'Complementaria Contaduría Pública', 'Complementaria en Administración de Empresas', 'Complementaria Ingeniería Comercial'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Sociales y Jurídicas',
                        'code' => 'PTO-SOC',
                        'carreras' => ['Licenciatura en Derecho', 'Licenciatura en Arte y Escultura'],
                    ],
                ],
            ],
            [
                'name' => 'Sede Guayaramerín (Beni)',
                'code' => 'GUA',
                'color' => '#ca8a04',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'GUA-SALUD',
                        'carreras' => ['Licenciatura en Medicina', 'Licenciatura en Fisioterapia y Kinesiología', 'Licenciatura en Bioquímica y Farmacia', 'Licenciatura en Fonoaudiología', 'Licenciatura en Nutrición y Dietética'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Empresariales',
                        'code' => 'GUA-ECON',
                        'carreras' => ['Licenciatura en Ingeniería Comercial'],
                    ],
                ],
            ],
            [
                'name' => 'Sede Caranavi (Los Yungas)',
                'code' => 'CVI',
                'color' => '#0284c7',
                'facultades' => [
                    [
                        'name' => 'Facultad de Ciencias de la Salud',
                        'code' => 'CVI-SALUD',
                        'carreras' => ['Licenciatura en Enfermería'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Económicas y Financieras',
                        'code' => 'CVI-ECON',
                        'carreras' => ['Licenciatura en Contaduría Pública', 'Licenciatura en Administración de Empresas'],
                    ],
                    [
                        'name' => 'Facultad de Ciencias Sociales y Jurídicas',
                        'code' => 'CVI-SOC',
                        'carreras' => ['Licenciatura en Derecho'],
                    ],
                ],
            ],
        ];

        foreach ($sedesData as $sData) {
            $sedeCat = $addCat($sData['name'], $rootSedes->id, $sData['code'], $sData['color'], 'sym_r_location_on', true);

            foreach ($sData['facultades'] as $fData) {
                $facCat = $addCat($fData['name'], $sedeCat->id, $fData['code'], $sData['color'], 'sym_r_account_balance', true);

                foreach ($fData['carreras'] as $carreraName) {
                    $code = $carreraCodeMap[$carreraName] ?? strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $carreraName), 0, 6));
                    $addCat($carreraName, $facCat->id, $code, $sData['color'], 'sym_r_school', true);
                }
            }
        }

        // ==========================================
        // 2. POSTGRADO & EDUCACIÓN CONTINUA
        // ==========================================
        $rootPostgrado = $addCat('Postgrado & Educación Continua', null, 'POSTGRADO', '#7c3aed', 'sym_r_workspace_premium', true);
        
        $dipCat = $addCat('Diplomados', $rootPostgrado->id, 'POST-DIP', '#8b5cf6', 'sym_r_badge', true);
        $addCat('Diplomado en Educación Superior basado en Competencias', $dipCat->id, 'DIP-EDUSUP', '#8b5cf6', 'sym_r_school', true);
        $addCat('Diplomado en Conciliación y Resolución de Controversias', $dipCat->id, 'DIP-CONCIL', '#8b5cf6', 'sym_r_gavel', true);
        $addCat('Diplomado en Auditoría y Gestión de Calidad en Enfermería', $dipCat->id, 'DIP-AUDENF', '#8b5cf6', 'sym_r_medical_services', true);
        $addCat('Diplomados en Ciencias de la Salud y Odontología', $dipCat->id, 'DIP-SALUD', '#8b5cf6', 'sym_r_health_and_safety', true);

        $masCat = $addCat('Maestrías & Especialidades', $rootPostgrado->id, 'POST-MAE', '#6d28d9', 'sym_r_military_tech', true);
        $addCat('Especialidades Médicas y Odontológicas', $masCat->id, 'ESP-MED', '#6d28d9', 'sym_r_healing', true);
        $addCat('Maestrías en Tecnología y Gestión Empresarial', $masCat->id, 'MAE-GEST', '#6d28d9', 'sym_r_trending_up', true);

        $curCat = $addCat('Cursos de Actualización & Talleres', $rootPostgrado->id, 'POST-CUR', '#a78bfa', 'sym_r_menu_book', true);
        $addCat('Curso de Formación para Investigadores', $curCat->id, 'CUR-INVEST', '#a78bfa', 'sym_r_biotech', true);
        $addCat('Certificaciones & Talleres Continuos', $curCat->id, 'CUR-TALLER', '#a78bfa', 'sym_r_verified', true);

        // ==========================================
        // 3. UNICINE (FESTIVAL DE CINE)
        // ==========================================
        $rootUnicine = $addCat('UNICINE Film Festival', null, 'UNICINE', '#ec4899', 'sym_r_movie', true);
        $addCat('Convocatoria & Postulación de Obras (Ficción, Doc, Animación)', $rootUnicine->id, 'UNI-POST', '#db2777', 'sym_r_upload_file', true);
        $addCat('Programación, Sedes & Cartelera Oficial', $rootUnicine->id, 'UNI-PROG', '#db2777', 'sym_r_theaters', true);
        $addCat('Talleres, Charlas & Masterclasses con Directores', $rootUnicine->id, 'UNI-TALLER', '#db2777', 'sym_r_co_present', true);
        $addCat('Prensa, Cobertura & Voluntariado UNICINE', $rootUnicine->id, 'UNI-PRENSA', '#db2777', 'sym_r_campaign', true);

        // ==========================================
        // 4. ADMISIONES & SERVICIOS TRANSVERSALES
        // ==========================================
        $rootAdmisiones = $addCat('Admisiones, Becas & Servicios', null, 'ADM-SERV', '#f59e0b', 'sym_r_support_agent', true);
        $addCat('Requisitos de Inscripción (Nacionales)', $rootAdmisiones->id, 'REQ-NAC', '#d97706', 'sym_r_description', true);
        $addCat('Estudiantes Internacionales (Brasil, Perú, etc.)', $rootAdmisiones->id, 'EST-INT', '#d97706', 'sym_r_public', true);
        $addCat('Convalidaciones, Traspasos & Homologaciones', $rootAdmisiones->id, 'CONVAL', '#d97706', 'sym_r_sync_alt', true);
        $addCat('Beca FUNDECYT & Convenios Institucionales', $rootAdmisiones->id, 'BECA-FD', '#d97706', 'sym_r_card_giftcard', true);
        $addCat('Clínicas Odontológicas & Veterinarias UNITEPC', $rootAdmisiones->id, 'CLIN-SERV', '#d97706', 'sym_r_local_hospital', true);
        $addCat('Plataformas Virtuales (Moodle / Clases)', $rootAdmisiones->id, 'PLAT-VIRT', '#d97706', 'sym_r_devices', true);
        $addCat('Caja, Pagos & Planes de Financiamiento', $rootAdmisiones->id, 'CAJA-FIN', '#d97706', 'sym_r_payments', true);

        $this->info("   -> Árbol de categorías sembrado con éxito ({$sortIndex} nodos con códigos oficiales).");
    }

    private function seedRegionalOperatorsAndQueues(Organization $org): void
    {
        $this->line("-> Sembrando Operadores Regionales Oficiales para {$org->name}...");

        // Lista de 5 operadores oficiales solicitados por el usuario
        // Contraseña = password, email = nombre@unitepc.com.bo
        $operators = [
            [
                'name' => 'Anet',
                'email' => 'anet@unitepc.com.bo',
                'sedes' => ['Cochabamba (CBA)'],
            ],
            [
                'name' => 'Juan Pablo',
                'email' => 'juanpablo@unitepc.com.bo',
                'sedes' => ['La Paz (LPZ)', 'Caranavi (CVI)'],
            ],
            [
                'name' => 'Yerko',
                'email' => 'yerko@unitepc.com.bo',
                'sedes' => ['El Alto (EAL)', 'Ivirgarzama (IVI)'],
            ],
            [
                'name' => 'Johana',
                'email' => 'johana@unitepc.com.bo',
                'sedes' => ['Santa Cruz (STC)', 'Guayaramerín (GUA)'],
            ],
            [
                'name' => 'Mauricio',
                'email' => 'mauricio@unitepc.com.bo',
                'sedes' => ['Puerto Quijarro (PTO)', 'Cobija (CBJ)'],
            ],
        ];

        $operatorUsers = [];

        foreach ($operators as $opData) {
            $user = User::where('email', $opData['email'])->first();
            if (!$user) {
                $user = User::create([
                    'name' => $opData['name'],
                    'email' => $opData['email'],
                    'password' => Hash::make('password'),
                    'current_organization_id' => $org->id,
                    'presence_status' => 'online',
                    'preferences' => User::defaultPreferences(),
                ]);
            } else {
                $user->update([
                    'name' => $opData['name'],
                    'password' => Hash::make('password'),
                    'current_organization_id' => $org->id,
                    'presence_status' => 'online',
                ]);
            }

            // Asociar como agente a la organización
            if (!$user->organizations()->where('organizations.id', $org->id)->exists()) {
                $user->organizations()->attach($org->id, [
                    'id' => (string) Str::ulid(),
                    'role' => 'agent',
                ]);
            }

            $operatorUsers[$opData['name']] = $user;
        }

        // Crear/Actualizar Filas de Atención vinculadas a los operadores de cada sede
        $queuesData = [
            [
                'name' => 'Cochabamba (Anet)',
                'color' => '#0d9488',
                'order' => 1,
                'operators' => ['Anet'],
            ],
            [
                'name' => 'La Paz & Caranavi (Juan Pablo)',
                'color' => '#2563eb',
                'order' => 2,
                'operators' => ['Juan Pablo'],
            ],
            [
                'name' => 'El Alto & Ivirgarzama (Yerko)',
                'color' => '#4f46e5',
                'order' => 3,
                'operators' => ['Yerko'],
            ],
            [
                'name' => 'Santa Cruz & Guayaramerín (Johana)',
                'color' => '#059669',
                'order' => 4,
                'operators' => ['Johana'],
            ],
            [
                'name' => 'Puerto Quijarro & Cobija (Mauricio)',
                'color' => '#ea580c',
                'order' => 5,
                'operators' => ['Mauricio'],
            ],
        ];

        foreach ($queuesData as $qData) {
            $queue = Queue::where('organization_id', $org->id)->where('name', $qData['name'])->first();
            if (!$queue) {
                $queue = Queue::create([
                    'organization_id' => $org->id,
                    'name' => $qData['name'],
                    'color' => $qData['color'],
                    'order_index' => $qData['order'],
                    'is_active' => true,
                    'greeting_message' => "Hola, bienvenido al canal oficial de UNITEPC ({$qData['name']}). Un asesor te atenderá a la brevedad.",
                ]);
            }

            $queueUserIds = [];
            foreach ($qData['operators'] as $opName) {
                if (isset($operatorUsers[$opName])) {
                    $queueUserIds[] = $operatorUsers[$opName]->id;
                }
            }

            if (!empty($queueUserIds)) {
                $queue->users()->syncWithoutDetaching($queueUserIds);
            }
        }

        $this->info("   -> 5 Operadores creados con password 'password' y asignados a sus sedes.");
    }

    private function seedBusinessHours(Organization $org): void
    {
        $this->line("-> Sincronizando Horario Laboral Oficial UNITEPC para {$org->name}...");

        $autoReplyMsg = '¡Hola! Gracias por comunicarte con nosotros. En este momento nos encontramos fuera de nuestro horario de atención habitual. Tu mensaje ha sido registrado y un asesor te responderá a la brevedad tan pronto retomemos actividades. ¡Agradecemos tu paciencia!';

        // Horarios solicitados:
        // Lunes a Viernes: 07:45 a 18:30 (con rangos mañana y tarde)
        // Sábado: 08:15 a 12:30
        // Domingo: Cerrado
        $scheduleDays = [
            'monday' => [
                'enabled' => true,
                'start_time' => '07:45',
                'end_time' => '18:30',
                'slots' => [
                    ['name' => 'Mañana', 'start_time' => '07:45', 'end_time' => '12:30', 'enabled' => true],
                    ['name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '18:30', 'enabled' => true],
                ],
            ],
            'tuesday' => [
                'enabled' => true,
                'start_time' => '07:45',
                'end_time' => '18:30',
                'slots' => [
                    ['name' => 'Mañana', 'start_time' => '07:45', 'end_time' => '12:30', 'enabled' => true],
                    ['name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '18:30', 'enabled' => true],
                ],
            ],
            'wednesday' => [
                'enabled' => true,
                'start_time' => '07:45',
                'end_time' => '18:30',
                'slots' => [
                    ['name' => 'Mañana', 'start_time' => '07:45', 'end_time' => '12:30', 'enabled' => true],
                    ['name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '18:30', 'enabled' => true],
                ],
            ],
            'thursday' => [
                'enabled' => true,
                'start_time' => '07:45',
                'end_time' => '18:30',
                'slots' => [
                    ['name' => 'Mañana', 'start_time' => '07:45', 'end_time' => '12:30', 'enabled' => true],
                    ['name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '18:30', 'enabled' => true],
                ],
            ],
            'friday' => [
                'enabled' => true,
                'start_time' => '07:45',
                'end_time' => '18:30',
                'slots' => [
                    ['name' => 'Mañana', 'start_time' => '07:45', 'end_time' => '12:30', 'enabled' => true],
                    ['name' => 'Tarde', 'start_time' => '14:00', 'end_time' => '18:30', 'enabled' => true],
                ],
            ],
            'saturday' => [
                'enabled' => true,
                'start_time' => '08:15',
                'end_time' => '12:30',
                'slots' => [
                    ['name' => 'Mañana', 'start_time' => '08:15', 'end_time' => '12:30', 'enabled' => true],
                ],
            ],
            'sunday' => [
                'enabled' => false,
                'start_time' => '09:00',
                'end_time' => '13:00',
                'slots' => [],
            ],
        ];

        $config = BusinessHoursConfig::where('organization_id', $org->id)
            ->where('scope_type', 'general')
            ->orderByDesc('is_default')
            ->first();

        if (!$config) {
            $config = BusinessHoursConfig::create([
                'organization_id' => $org->id,
                'name' => 'Horario General UNITEPC',
                'scope_type' => 'general',
                'is_active' => true,
                'is_default' => true,
                'validity_type' => 'immediate',
                'timezone' => 'America/La_Paz',
                'schedule_days' => $scheduleDays,
                'auto_reply_enabled' => true,
                'auto_reply_message' => $autoReplyMsg,
            ]);
        } else {
            $config->update([
                'name' => 'Horario General UNITEPC',
                'scope_type' => 'general',
                'is_active' => true,
                'is_default' => true,
                'validity_type' => 'immediate',
                'timezone' => 'America/La_Paz',
                'schedule_days' => $scheduleDays,
                'auto_reply_enabled' => true,
                'auto_reply_message' => $autoReplyMsg,
            ]);
        }

        $this->info("   -> Horario Laboral UNITEPC configurado: Lun-Vie 07:45-18:30, Sáb 08:15-12:30.");
    }
}
