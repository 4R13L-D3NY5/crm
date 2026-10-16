<?php

namespace Database\Seeders;

use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Models\TenantInvoice;
use Illuminate\Database\Seeder;

class TenantBillingSeeder extends Seeder
{
    public function run(): void
    {
        $o1 = Organization::where('slug', 'crm-demo')->first();
        if ($o1) {
            $o1->update([
                'plan' => 'pro',
                'monthly_price_usd' => 250.00,
                'extra_whatsapp_lines' => 1,
                'max_whatsapp_lines' => 3,
                'max_social_accounts' => 12,
                'extra_social_accounts' => 0,
                'max_agents' => 7,
                'expires_at' => now()->addDays(18),
                'last_payment_at' => now()->subDays(12),
                'contact_name' => 'Carlos Mendoza',
                'contact_phone' => '+591 79326793',
                'contact_email' => 'carlos@democrm.com',
                'status' => 'active',
                'license_token' => 'XF-LIC-demo-pro-active',
            ]);

            TenantInvoice::firstOrCreate([
                'organization_id' => $o1->id,
                'concept' => 'Renovación Mensual Plan Pro Growth (+1 línea WhatsApp)',
            ], [
                'amount_usd' => 250.00,
                'period_start' => now()->subDays(12)->toDateString(),
                'period_end' => now()->addDays(18)->toDateString(),
                'payment_method' => 'transfer',
                'reference_code' => 'TRF-98231',
                'paid_at' => now()->subDays(12),
                'notes' => 'Pago verificado por banco.',
            ]);
        }

        $o2 = Organization::where('slug', 'crm-andina')->first();
        if ($o2) {
            $o2->update([
                'plan' => 'basic',
                'monthly_price_usd' => 120.00,
                'extra_whatsapp_lines' => 0,
                'max_whatsapp_lines' => 1,
                'max_social_accounts' => 8,
                'extra_social_accounts' => 0,
                'max_agents' => 3,
                'expires_at' => now()->addDays(3),
                'last_payment_at' => now()->subDays(27),
                'contact_name' => 'Mariana Rojas',
                'contact_phone' => '+591 63921086',
                'contact_email' => 'mariana@andinacorp.bo',
                'status' => 'active',
                'license_token' => 'XF-LIC-andina-basic-warning',
            ]);
        }

        $o3 = Organization::firstOrCreate(
            ['slug' => 'inversiones-valle'],
            [
                'name' => 'Inversiones del Valle',
                'plan' => 'elite',
                'monthly_price_usd' => 430.00,
                'extra_whatsapp_lines' => 2,
                'max_whatsapp_lines' => 6,
                'max_social_accounts' => 16,
                'extra_social_accounts' => 0,
                'max_agents' => 15,
                'expires_at' => now()->addDays(28),
                'last_payment_at' => now()->subDays(2),
                'contact_name' => 'Rodrigo Vaca',
                'contact_phone' => '+591 78912345',
                'contact_email' => 'rodrigo@delvalle.com',
                'status' => 'active',
                'license_token' => 'XF-LIC-valle-elite-active',
            ]
        );

        $o4 = Organization::firstOrCreate(
            ['slug' => 'logistica-express'],
            [
                'name' => 'Logística Express',
                'plan' => 'basic',
                'monthly_price_usd' => 120.00,
                'extra_whatsapp_lines' => 0,
                'max_whatsapp_lines' => 1,
                'max_social_accounts' => 8,
                'extra_social_accounts' => 0,
                'max_agents' => 3,
                'expires_at' => now()->subDays(2),
                'last_payment_at' => now()->subDays(32),
                'contact_name' => 'Fabiana Soliz',
                'contact_phone' => '+591 71234567',
                'contact_email' => 'fabiana@express.com',
                'status' => 'past_due',
                'license_token' => 'XF-LIC-express-expired',
            ]
        );
    }
}
