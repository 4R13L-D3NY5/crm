<?php

namespace App\Console\Commands;

use App\Modules\Automations\Models\Campaign;
use App\Modules\Automations\Models\CampaignRecipient;
use App\Modules\Companies\Models\Company;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\ConversationAssignment;
use App\Modules\Conversations\Models\Message;
use App\Modules\Deals\Models\Deal;
use App\Modules\Deals\Models\DealStageHistory;
use App\Modules\WhatsApp\Models\WhatsAppAccount;
use App\Modules\WhatsApp\Models\WhatsAppMessageMapping;
use App\Modules\WhatsApp\Models\WhatsAppWebhookEvent;
use Database\Seeders\CleanProductionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanProductionEnvironmentCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'crm:clean-environment {--force : Forzar ejecución sin pedir confirmación}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Limpia todos los datos de prueba del CRM (chats, contactos, cuentas mock) y prepara la instancia para producción';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (!$this->option('force')) {
            if (!$this->confirm('¿Estás seguro de que deseas vaciar todos los contactos, chats y conexiones de prueba? Esta acción es irreversible.')) {
                $this->warn('Operación cancelada.');
                return self::SUCCESS;
            }
        }

        $this->info('Iniciando purga de datos demo y residuales...');

        DB::transaction(function () {
            // 1. Purgar campañas y despachos
            CampaignRecipient::query()->delete();
            Campaign::query()->delete();

            // 2. Purgar mensajes y conversaciones
            WhatsAppMessageMapping::query()->delete();
            WhatsAppWebhookEvent::query()->delete();
            Message::query()->delete();
            ConversationAssignment::query()->delete();
            Conversation::query()->delete();

            // 3. Purgar canales y sesiones de WhatsApp
            WhatsAppAccount::query()->delete();

            // 4. Purgar deals y pipeline histórico
            DealStageHistory::query()->delete();
            Deal::query()->delete();

            // 5. Purgar contactos y empresas demo
            DB::table('contact_tag')->delete();
            Contact::query()->delete();
            Company::query()->delete();
        });

        $this->info('Datos demo eliminados correctamente.');

        // 6. Ejecutar el seeder limpio
        $this->info('Ejecutando CleanProductionSeeder para sembrar UNITEPC y usuarios base...');
        $seeder = new CleanProductionSeeder();
        $seeder->run();

        $this->info('¡Entorno de producción 100% limpio y listo para vincular canales reales!');
        return self::SUCCESS;
    }
}
