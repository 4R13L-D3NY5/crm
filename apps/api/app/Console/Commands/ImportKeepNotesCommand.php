<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Notes\Services\KeepImportService;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Console\Command;

class ImportKeepNotesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notes:import-keep
                            {zip_path : Ruta absoluta o relativa al archivo ZIP de Google Takeout}
                            {--org= : ID o slug de la organización destino}
                            {--user= : ID o correo del usuario que figurará como autor}
                            {--no-ai : Desactivar la indexación automática en la Base de Conocimiento de IA}
                            {--force : Omitir confirmación interactiva}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Importa notas desde un archivo ZIP de Google Takeout (Keep) y las sincroniza con la Base de Conocimiento de IA';

    /**
     * Execute the console command.
     */
    public function handle(KeepImportService $importService): int
    {
        $zipPath = $this->argument('zip_path');

        if (!file_exists($zipPath)) {
            $this->error("El archivo ZIP no existe en la ruta: {$zipPath}");
            return self::FAILURE;
        }

        $this->info("=================================================");
        $this->info("  IMPORTADOR INSTITUCIONAL DE GOOGLE KEEP (UNITEPC)");
        $this->info("=================================================");
        $this->line("Leyendo archivo: <comment>{$zipPath}</comment>");

        // 1. Resolver Organización
        $orgInput = $this->option('org');
        $org = null;
        if ($orgInput) {
            $org = Organization::where('id', $orgInput)
                ->orWhere('slug', $orgInput)
                ->first();
            if (!$org) {
                $this->error("Organización no encontrada con el criterio: {$orgInput}");
                return self::FAILURE;
            }
        } else {
            $org = Organization::first();
            if (!$org) {
                $this->error("No hay organizaciones registradas en la base de datos.");
                return self::FAILURE;
            }
        }

        $this->info("Organización destino: <fg=cyan>{$org->name}</> [ID: {$org->id}]");

        // 2. Resolver Usuario autor
        $userInput = $this->option('user');
        $user = null;
        if ($userInput) {
            $user = User::where('id', $userInput)
                ->orWhere('email', $userInput)
                ->first();
            if (!$user) {
                $this->error("Usuario no encontrado con el criterio: {$userInput}");
                return self::FAILURE;
            }
        } else {
            // Primer usuario administrador o perteneciente a la organización
            $user = User::where('current_organization_id', $org->id)->first() ?: User::first();
            if (!$user) {
                $this->error("No hay usuarios disponibles en la organización.");
                return self::FAILURE;
            }
        }

        // Asegurar que el usuario tenga el current_organization_id correcto
        $user->current_organization_id = $org->id;

        $this->info("Usuario asignado: <fg=cyan>{$user->name}</> ({$user->email})");

        // 3. Previsualizar el ZIP
        $this->line("Procesando estructura y extrayendo notas...");
        try {
            $preview = $importService->previewZip($zipPath);
        } catch (\Throwable $e) {
            $this->error("Error al procesar el archivo ZIP: " . $e->getMessage());
            return self::FAILURE;
        }

        $notes = $preview['notes'];
        $totalDetected = count($notes);

        $this->info("Se detectaron <fg=green;options=bold>{$totalDetected}</> notas válidas de Google Keep.");

        // 4. Mostrar muestra de las primeras 8 notas
        $sampleRows = [];
        foreach (array_slice($notes, 0, 8) as $n) {
            $sampleRows[] = [
                \Illuminate\Support\Str::limit($n['title'], 35),
                '#' . ($n['shortcut'] ?? '-'),
                $n['keep_color'],
                \Illuminate\Support\Str::limit(str_replace("\n", " ", $n['content']), 40),
            ];
        }

        $this->table(['Título', 'Atajo (#)', 'Color Keep', 'Contenido Preview'], $sampleRows);

        $indexAi = !$this->option('no-ai');
        $this->line("Indexación en Base de Conocimiento IA (RAG): " . ($indexAi ? "<fg=green>ACTIVADA</>" : "<fg=yellow>DESACTIVADA</>"));

        // 5. Confirmación si no tiene --force
        if (!$this->option('force')) {
            if (!$this->confirm("¿Deseas importar estas {$totalDetected} notas a la base de datos?", true)) {
                $this->warn("Operación cancelada por el usuario.");
                return self::SUCCESS;
            }
        }

        // 6. Importar notas
        $this->info("Importando notas e indexando fragmentos en la Base de Conocimiento...");
        $startTime = microtime(true);

        try {
            $result = $importService->importBatch($user, $notes, $indexAi);
        } catch (\Throwable $e) {
            $this->error("Fallo durante la importación: " . $e->getMessage());
            return self::FAILURE;
        }

        $duration = round(microtime(true) - $startTime, 2);

        $this->newLine();
        $this->info("=================================================");
        $this->info("  IMPORTACIÓN COMPLETADA CON ÉXITO");
        $this->info("=================================================");
        $this->line("Notas importadas en CRM: <fg=green;options=bold>{$result['imported_count']}</>");
        $this->line("Fragmentos de Conocimiento IA creados: <fg=cyan;options=bold>{$result['ai_chunks_created']}</>");
        $this->line("Tiempo total: <comment>{$duration}s</comment>");
        $this->info("=================================================");

        return self::SUCCESS;
    }
}
