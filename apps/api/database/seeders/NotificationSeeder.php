<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Tenancy\Models\Organization;
use Illuminate\Database\Seeder;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $organizations = Organization::all();

        foreach ($organizations as $org) {
            $user = $org->users()->first();

            // 1. Notificación de Chat WhatsApp
            AppNotification::create([
                'organization_id' => $org->id,
                'user_id' => $user?->id,
                'type' => 'chat',
                'title' => 'Nuevo mensaje de WhatsApp',
                'message' => 'Carlos Mendoza (+591 70123456) envió una consulta: "¿Qué planes tienen disponibles?".',
                'action_url' => '/app/conversations',
                'data' => ['sender' => 'Carlos Mendoza', 'phone' => '+591 70123456'],
                'read_at' => null,
                'created_at' => now()->subMinutes(12),
            ]);

            // 2. Notificación Financiera / SaaS
            AppNotification::create([
                'organization_id' => $org->id,
                'user_id' => $user?->id,
                'type' => 'billing',
                'title' => 'Estado de Suscripción',
                'message' => "Tu suscripción al Plan " . strtoupper($org->plan ?? 'BÁSICO') . " se encuentra activa con vigencia hasta " . ($org->expires_at?->format('d/m/Y') ?? 'fin de mes') . ".",
                'action_url' => '/app/superadmin',
                'data' => ['plan' => $org->plan, 'status' => $org->status],
                'read_at' => null,
                'created_at' => now()->subHours(2),
            ]);

            // 3. Notificación de Hentle-AI Copilot
            AppNotification::create([
                'organization_id' => $org->id,
                'user_id' => $user?->id,
                'type' => 'ai',
                'title' => 'Sugerencia Hentle-AI™',
                'message' => 'Se detectó alta intención de compra en 3 conversaciones recientes. Considera enviar cotización.',
                'action_url' => '/app/deals',
                'data' => ['confidence' => 0.94],
                'read_at' => null,
                'created_at' => now()->subHours(5),
            ]);

            // 4. Notificación del Sistema (ya leída para tener variedad)
            AppNotification::create([
                'organization_id' => $org->id,
                'user_id' => $user?->id,
                'type' => 'system',
                'title' => 'Bienvenido a XpertiFlow CRM V2',
                'message' => 'El entorno local se ha actualizado con soporte de multitenancy, contabilidad MRR y centro de control.',
                'action_url' => '/app',
                'data' => ['version' => '2.0.0'],
                'read_at' => now()->subDay(),
                'created_at' => now()->subDay(),
            ]);
        }
    }
}
