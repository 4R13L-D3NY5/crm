<?php

namespace App\Modules\Tenancy\Services;

use App\Modules\HentleAi\Services\HentleAiCopilotService;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Models\TenantInvoice;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SuperAdminBillingService
{
    public function __construct(
        protected HentleAiCopilotService $aiService,
    ) {}

    /**
     * Calcula las tarifas base y add-ons según el plan.
     */
    public function getPlanPricing(string $plan): array
    {
        return match (strtolower($plan)) {
            'pro' => [
                'name' => 'Pro Growth',
                'base_monthly' => 230.00,
                'base_annual_monthly' => 195.00,
                'included_phones' => 2,
                'included_social' => 12,
                'included_agents' => 7,
                'extra_phone_rate' => 20.00,
                'extra_social_rate' => 10.00,
                'extra_agent_rate' => 15.00,
            ],
            'elite' => [
                'name' => 'Elite IA Enterprise',
                'base_monthly' => 390.00,
                'base_annual_monthly' => 330.00,
                'included_phones' => 4,
                'included_social' => 16,
                'included_agents' => 15,
                'extra_phone_rate' => 20.00,
                'extra_social_rate' => 10.00,
                'extra_agent_rate' => 12.00,
            ],
            default => [
                'name' => 'Básico Starter',
                'base_monthly' => 120.00,
                'base_annual_monthly' => 102.00,
                'included_phones' => 1,
                'included_social' => 8,
                'included_agents' => 3,
                'extra_phone_rate' => 20.00,
                'extra_social_rate' => 10.00,
                'extra_agent_rate' => 18.00,
            ],
        };
    }

    /**
     * Calcula el costo mensual recurrente (MRR) total de una organización.
     */
    public function calculateTenantMonthlyPrice(Organization $org): float
    {
        $planMeta = $this->getPlanPricing($org->plan ?? 'basic');
        $base = $org->billing_cycle === 'annual' ? $planMeta['base_annual_monthly'] : $planMeta['base_monthly'];
        $phonesExtra = ($org->extra_whatsapp_lines ?? 0) * $planMeta['extra_phone_rate'];
        $socialExtra = ($org->extra_social_accounts ?? 0) * ($planMeta['extra_social_rate'] ?? 10.00);
        $agentsExtra = ($org->extra_agents ?? 0) * $planMeta['extra_agent_rate'];

        return round($base + $phonesExtra + $socialExtra + $agentsExtra, 2);
    }

    /**
     * Obtiene el listado consolidado de empresas con métricas y alertas calculadas.
     */
    public function getTenantsWithMetrics(): array
    {
        $orgs = Organization::withCount(['users'])->get();

        $totalMrr = 0.0;
        $activeCount = 0;
        $warningCount = 0;
        $expiredCount = 0;
        $suspendedCount = 0;
        $totalPhonesLeased = 0;

        $items = $orgs->map(function (Organization $org) use (
            &$totalMrr,
            &$activeCount,
            &$warningCount,
            &$expiredCount,
            &$suspendedCount,
            &$totalPhonesLeased
        ) {
            $mrr = $this->calculateTenantMonthlyPrice($org);
            $totalMrr += $mrr;

            $now = Carbon::now();
            $expiresAt = $org->expires_at ? Carbon::parse($org->expires_at) : null;
            $daysRemaining = $expiresAt ? (int) $now->diffInDays($expiresAt, false) : 0;

            // Determinar estado de salud
            if ($org->status === 'suspended') {
                $health = 'suspended';
                $suspendedCount++;
            } elseif ($expiresAt === null || $daysRemaining < 0) {
                $health = 'expired';
                $expiredCount++;
            } elseif ($daysRemaining <= 7) {
                $health = 'warning';
                $warningCount++;
            } else {
                $health = 'active';
                $activeCount++;
            }

            $planMeta = $this->getPlanPricing($org->plan ?? 'basic');
            $totalPhones = $planMeta['included_phones'] + ($org->extra_whatsapp_lines ?? 0);
            $totalPhonesLeased += $totalPhones;

            return [
                'id' => $org->id,
                'name' => $org->name,
                'slug' => $org->slug,
                'plan' => $org->plan ?? 'basic',
                'plan_name' => $planMeta['name'],
                'billing_cycle' => $org->billing_cycle ?? 'monthly',
                'status' => $org->status ?? 'active',
                'health_status' => $health,
                'days_remaining' => $daysRemaining,
                'expires_at' => $org->expires_at?->format('Y-m-d H:i') ?? 'Sin fecha asignada',
                'last_payment_at' => $org->last_payment_at?->format('Y-m-d H:i') ?? 'Sin pagos registrados',
                'monthly_mrr_usd' => $mrr,
                'phones_total' => $totalPhones,
                'extra_phones' => $org->extra_whatsapp_lines ?? 0,
                'social_total' => ($planMeta['included_social'] ?? 8) + ($org->extra_social_accounts ?? 0),
                'extra_social' => $org->extra_social_accounts ?? 0,
                'agents_total' => $planMeta['included_agents'] + ($org->extra_agents ?? 0),
                'extra_agents' => $org->extra_agents ?? 0,
                'users_count' => $org->users_count,
                'contact_name' => $org->contact_name ?? 'Administrador',
                'contact_phone' => $org->contact_phone ?? '',
                'contact_email' => $org->contact_email ?? '',
                'license_token' => $org->license_token,
            ];
        });

        return [
            'summary' => [
                'total_tenants' => $orgs->count(),
                'total_mrr_usd' => round($totalMrr, 2),
                'total_arr_usd' => round($totalMrr * 12, 2),
                'total_phones_leased' => $totalPhonesLeased,
                'active_count' => $activeCount,
                'warning_count' => $warningCount,
                'expired_count' => $expiredCount,
                'suspended_count' => $suspendedCount,
            ],
            'tenants' => $items,
        ];
    }

    /**
     * Renueva la suscripción de un cliente por N meses.
     */
    public function renewSubscription(
        Organization $org,
        int $months = 1,
        string $paymentMethod = 'transfer',
        ?string $referenceCode = null,
        ?string $notes = null
    ): array {
        $now = Carbon::now();
        $currentExpires = $org->expires_at ? Carbon::parse($org->expires_at) : null;

        // Si ya está vencido, renueva desde hoy. Si no, extiende la fecha actual.
        $baseDate = ($currentExpires && $currentExpires->isFuture()) ? $currentExpires : $now;
        $newExpires = $baseDate->copy()->addMonths($months);

        $monthlyPrice = $this->calculateTenantMonthlyPrice($org);
        $totalAmount = $monthlyPrice * $months;

        // Descuento si es anual (12 meses o ciclo anual)
        if ($months >= 12) {
            $totalAmount = round($totalAmount * 0.85, 2);
        }

        // Generar token de licencia firmado
        $licenseToken = $this->generateSignedLicenseToken($org->id, $org->plan, $newExpires);

        DB::transaction(function () use (
            $org,
            $newExpires,
            $now,
            $monthlyPrice,
            $totalAmount,
            $licenseToken,
            $months,
            $paymentMethod,
            $referenceCode,
            $notes
        ) {
            $org->update([
                'status' => 'active',
                'expires_at' => $newExpires,
                'last_payment_at' => $now,
                'monthly_price_usd' => $monthlyPrice,
                'license_token' => $licenseToken,
            ]);

            TenantInvoice::create([
                'organization_id' => $org->id,
                'amount_usd' => $totalAmount,
                'concept' => "Renovación de suscripción: {$months} mes(es) — Plan " . strtoupper($org->plan),
                'period_start' => now()->toDateString(),
                'period_end' => $newExpires->toDateString(),
                'payment_method' => $paymentMethod,
                'reference_code' => $referenceCode,
                'paid_at' => now(),
                'notes' => $notes,
            ]);
        });

        return [
            'success' => true,
            'new_expires_at' => $newExpires->format('Y-m-d H:i:s'),
            'amount_charged_usd' => $totalAmount,
            'license_token' => $licenseToken,
        ];
    }

    /**
     * Calcula la matemática exacta de prorrateo si el cliente cambia de plan a mitad de ciclo.
     */
    public function calculateProration(
        Organization $org,
        string $newPlan,
        int $newExtraPhones = 0,
        int $newExtraAgents = 0,
        int $newExtraSocial = 0
    ): array {
        $now = Carbon::now();
        $expiresAt = $org->expires_at ? Carbon::parse($org->expires_at) : $now->copy()->addDays(30);

        // Ciclo estándar de 30 días
        $daysRemaining = max(0, min(30, (int) $now->diffInDays($expiresAt, false)));
        $daysUsed = 30 - $daysRemaining;

        // Costo mensual del plan actual
        $currentMonthly = $this->calculateTenantMonthlyPrice($org);

        // Costo mensual del nuevo plan deseado
        $newPlanMeta = $this->getPlanPricing($newPlan);
        $newBase = $newPlanMeta['base_monthly'];
        $newPhonesCost = $newExtraPhones * $newPlanMeta['extra_phone_rate'];
        $newSocialCost = $newExtraSocial * ($newPlanMeta['extra_social_rate'] ?? 10.00);
        $newAgentsCost = $newExtraAgents * $newPlanMeta['extra_agent_rate'];
        $newMonthly = round($newBase + $newPhonesCost + $newSocialCost + $newAgentsCost, 2);

        // Saldo no consumido a favor del cliente
        $unusedCredit = round(($currentMonthly / 30) * $daysRemaining, 2);

        // Costo del nuevo plan por los días restantes
        $newPlanCostForRemainingDays = round(($newMonthly / 30) * $daysRemaining, 2);

        // Diferencia neta a cobrar (prorrateo)
        $proratedDifference = max(0.0, round($newPlanCostForRemainingDays - $unusedCredit, 2));

        return [
            'current_plan' => $org->plan,
            'current_monthly_price' => $currentMonthly,
            'new_plan' => $newPlan,
            'new_monthly_price' => $newMonthly,
            'days_used' => $daysUsed,
            'days_remaining' => $daysRemaining,
            'unused_credit_usd' => $unusedCredit,
            'new_plan_cost_remaining_days_usd' => $newPlanCostForRemainingDays,
            'prorated_charge_due_today_usd' => $proratedDifference,
        ];
    }

    /**
     * Aplica el cambio de plan y registra el pago prorrateado.
     */
    public function applyPlanChange(
        Organization $org,
        string $newPlan,
        int $newExtraPhones = 0,
        int $newExtraAgents = 0,
        int $newExtraSocial = 0,
        string $paymentMethod = 'transfer',
        ?string $referenceCode = null
    ): array {
        $proration = $this->calculateProration($org, $newPlan, $newExtraPhones, $newExtraAgents, $newExtraSocial);
        $dueToday = $proration['prorated_charge_due_today_usd'];

        DB::transaction(function () use (
            $org,
            $newPlan,
            $newExtraPhones,
            $newExtraAgents,
            $newExtraSocial,
            $dueToday,
            $paymentMethod,
            $referenceCode,
            $proration
        ) {
            $newPlanMeta = $this->getPlanPricing($newPlan);
            $newMonthly = $proration['new_monthly_price'];

            $org->update([
                'plan' => $newPlan,
                'extra_whatsapp_lines' => $newExtraPhones,
                'extra_agents' => $newExtraAgents,
                'extra_social_accounts' => $newExtraSocial,
                'max_whatsapp_lines' => $newPlanMeta['included_phones'] + $newExtraPhones,
                'max_social_accounts' => ($newPlanMeta['included_social'] ?? 8) + $newExtraSocial,
                'max_agents' => $newPlanMeta['included_agents'] + $newExtraAgents,
                'monthly_price_usd' => $newMonthly,
            ]);

            if ($dueToday > 0) {
                TenantInvoice::create([
                    'organization_id' => $org->id,
                    'amount_usd' => $dueToday,
                    'concept' => "Prorrateo por cambio de plan: {$org->plan} a {$newPlan} ({$proration['days_remaining']} días restantes)",
                    'payment_method' => $paymentMethod,
                    'reference_code' => $referenceCode,
                    'paid_at' => now(),
                    'notes' => 'Ajuste de ciclo en tiempo real calculado por SuperAdmin',
                ]);
            }
        });

        return [
            'success' => true,
            'proration' => $proration,
        ];
    }

    /**
     * Ajusta las cuotas adicionales de canales WhatsApp, redes y agentes con prorrateo exacto.
     */
    public function adjustQuotas(
        Organization $org,
        int $newExtraPhones,
        int $newExtraAgents,
        int $newExtraSocial = 0,
        string $paymentMethod = 'transfer',
        ?string $referenceCode = null,
        ?string $notes = null
    ): array {
        $proration = $this->calculateProration($org, $org->plan ?? 'basic', $newExtraPhones, $newExtraAgents, $newExtraSocial);
        $dueToday = $proration['prorated_charge_due_today_usd'];
        $planMeta = $this->getPlanPricing($org->plan ?? 'basic');

        DB::transaction(function () use (
            $org,
            $newExtraPhones,
            $newExtraAgents,
            $newExtraSocial,
            $dueToday,
            $paymentMethod,
            $referenceCode,
            $notes,
            $planMeta,
            $proration
        ) {
            $newMonthly = $proration['new_monthly_price'];

            $org->update([
                'extra_whatsapp_lines' => $newExtraPhones,
                'extra_agents' => $newExtraAgents,
                'extra_social_accounts' => $newExtraSocial,
                'max_whatsapp_lines' => $planMeta['included_phones'] + $newExtraPhones,
                'max_social_accounts' => ($planMeta['included_social'] ?? 8) + $newExtraSocial,
                'max_agents' => $planMeta['included_agents'] + $newExtraAgents,
                'monthly_price_usd' => $newMonthly,
            ]);

            if ($dueToday > 0) {
                TenantInvoice::create([
                    'organization_id' => $org->id,
                    'amount_usd' => $dueToday,
                    'concept' => "Ajuste de cuotas (+/- canales/redes/agentes) — Plan " . strtoupper($org->plan ?? 'BÁSICO') . " ({$proration['days_remaining']} días restantes)",
                    'payment_method' => $paymentMethod,
                    'reference_code' => $referenceCode,
                    'paid_at' => now(),
                    'notes' => $notes ?? "Ajuste de recursos: {$newExtraPhones} líneas WhatsApp extra, {$newExtraSocial} redes extra, {$newExtraAgents} agentes extra. Prorrateado.",
                ]);
            }
        });

        // Regenerar token firmado con las nuevas especificaciones
        $expiresAt = $org->expires_at ? Carbon::parse($org->expires_at) : Carbon::now()->addDays(30);
        $licenseToken = $this->generateSignedLicenseToken($org->id, $org->plan ?? 'basic', $expiresAt);
        $org->update(['license_token' => $licenseToken]);

        return [
            'success' => true,
            'message' => 'Cuotas de canales, redes sociales y agentes actualizadas exitosamente.',
            'proration' => $proration,
            'new_max_whatsapp_lines' => $planMeta['included_phones'] + $newExtraPhones,
            'new_max_social_accounts' => ($planMeta['included_social'] ?? 8) + $newExtraSocial,
            'new_max_agents' => $planMeta['included_agents'] + $newExtraAgents,
            'new_monthly_price_usd' => $proration['new_monthly_price'],
            'license_token' => $licenseToken,
        ];
    }

    /**
     * Alterna el estado de suspensión de una organización.
     */
    public function toggleSuspension(Organization $org): string
    {
        $newStatus = $org->status === 'suspended' ? 'active' : 'suspended';
        $org->update(['status' => $newStatus]);
        return $newStatus;
    }

    /**
     * Genera un Token de Licencia firmado criptográficamente con HMAC.
     */
    public function generateSignedLicenseToken(string $orgId, string $plan, Carbon $expiresAt): string
    {
        $payload = [
            'iss' => 'XpertiFlow-SaaS-Master',
            'org_id' => $orgId,
            'plan' => $plan,
            'valid_until' => $expiresAt->timestamp,
            'issued_at' => now()->timestamp,
            'nonce' => Str::random(12),
        ];

        $encodedPayload = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $encodedPayload, config('app.key') ?: 'xpertiflow-secret-license-key');

        return "XF-LIC-{$encodedPayload}.{$signature}";
    }

    /**
     * Utiliza Hentle-AI para redactar un mensaje de cobro/recordatorio persuasivo y personalizado por WhatsApp.
     */
    public function generateAiPaymentReminder(Organization $org, string $tone = 'friendly'): array
    {
        $mrr = $this->calculateTenantMonthlyPrice($org);
        $planMeta = $this->getPlanPricing($org->plan ?? 'basic');
        $expires = $org->expires_at ? Carbon::parse($org->expires_at)->format('d/m/Y') : 'próximamente';
        $days = $org->expires_at ? (int) Carbon::now()->diffInDays(Carbon::parse($org->expires_at), false) : 0;
        $clientName = $org->contact_name ?: $org->name;

        // Prompt estructurado para Hentle-AI
        $prompt = "Actúa como el asistente de facturación y cobranza del CRM XpertiFlow.
Redacta un mensaje de WhatsApp para el cliente con los siguientes datos:
- Cliente / Empresa: {$clientName} ({$org->name})
- Plan Contratado: {$planMeta['name']}
- Monto a renovar: \${$mrr} USD mensuales
- Fecha de corte / vencimiento: {$expires} (quedan {$days} días)
- Tono solicitado: {$tone} (opciones: 'friendly' para aviso cortés y preventivo; 'formal' para tono ejecutivo corporativo; 'urgent' para último aviso antes de pausar el servicio).
- Incluye recordatorio sutil de que al pagar anualmente ahorran un 15% (2 meses gratis).
- Termina con una llamada a la acción clara para confirmar el pago por este mismo chat.
No agregues explicaciones fuera del mensaje, solo devuelve el texto formateado con emojis elegantes para enviar directamente a WhatsApp.";

        try {
            $config = $this->aiService->getEffectiveConfig($org->id);
            $reply = null;

            // Intentar invocar al proveedor configurado en HentleAi
            if (!empty($config['api_key'])) {
                $response = Http::withHeaders([
                    'Authorization' => "Bearer {$config['api_key']}",
                    'Content-Type' => 'application/json',
                ])->timeout(15)->post($config['base_url'] ?: 'https://api.minimaxi.chat/v1/text/chatcompletion_v2', [
                    'model' => $config['model'] ?: 'MiniMax-Text-01',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres el asistente financiero de XpertiFlow CRM.'],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $reply = $json['choices'][0]['message']['content'] ?? null;
                }
            }

            // Fallback cognitivo enriquecido si la API externa está offline
            if (!$reply) {
                if ($tone === 'urgent') {
                    $reply = "⚠️ *Último aviso de renovación — XpertiFlow CRM*\n\nEstimado/a *{$clientName}*, te informamos que tu suscripción al *{$planMeta['name']}* vence el *{$expires}* (hoy o en pocas horas).\n\nPara evitar la interrupción del servicio y mantener la conexión de tus líneas de WhatsApp activas, por favor realiza el pago de *$" . number_format($mrr, 2) . " USD*.\n\n_Paga anualmente con un 15% de descuento por solo $" . number_format($mrr * 10.2, 2) . " USD/año._\n\nPor favor envíanos tu comprobante respondiendo a este mensaje. ¡Gracias!";
                } elseif ($tone === 'formal') {
                    $reply = "🏛️ *Recordatorio de Facturación — XpertiFlow CRM*\n\nEstimado/a *{$clientName}*,\n\nLe notificamos cordialmente que el ciclo de facturación de su cuenta correspondiente al *{$planMeta['name']}* vencerá el próximo *{$expires}*.\n\n*Monto de renovación:* $" . number_format($mrr, 2) . " USD\n*Líneas y agentes incluidos:* Activos sin interrupción\n\nSi desea renovar en modalidad anual con un 15% de bonificación o coordinar la factura correspondiente, quedamos atentos a su respuesta.\n\nAtentamente,\n*Administración XpertiFlow CRM*";
                } else {
                    $reply = "👋 ¡Hola *{$clientName}*! Esperamos que tu equipo esté vendiendo mucho con *XpertiFlow CRM* 🚀\n\nTe escribimos para recordarte con tiempo que tu suscripción al *{$planMeta['name']}* tiene fecha de corte para el *{$expires}* (en {$days} días).\n\n💳 *Monto mensual:* $" . number_format($mrr, 2) . " USD\n💡 *Tip de ahorro:* Si renuevas por 1 año te regalamos 2 meses (-15% de descuento).\n\n¿Deseas que te enviemos los datos de transferencia o link de pago? ¡Un saludo cordial!";
                }
            }

            return [
                'success' => true,
                'message_text' => trim($reply),
                'contact_phone' => $org->contact_phone,
                'whatsapp_url' => 'https://wa.me/' . preg_replace('/[^0-9]/', '', (string) $org->contact_phone) . '?text=' . urlencode(trim($reply)),
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Utiliza Hentle-AI para analizar la salud del cliente y recomendar acciones comerciales (Upsell o Retención Churn).
     */
    public function generateAiHealthAudit(Organization $org): array
    {
        $planMeta = $this->getPlanPricing($org->plan ?? 'basic');
        $mrr = $this->calculateTenantMonthlyPrice($org);
        $usersCount = $org->users()->count();
        $daysRemaining = $org->expires_at ? (int) Carbon::now()->diffInDays(Carbon::parse($org->expires_at), false) : 0;

        $prompt = "Analiza el estado de este cliente de XpertiFlow CRM:
- Empresa: {$org->name}
- Plan: {$planMeta['name']} ($" . $mrr . " USD/mes)
- Agentes usados: {$usersCount} de {$planMeta['included_agents']} permitidos
- Días para vencimiento: {$daysRemaining} días
- Estado actual: {$org->status}
Dame un dictamen breve en formato JSON con:
1. 'churn_risk': 'bajo', 'medio' o 'alto'
2. 'upsell_opportunity': 'ninguna', 'lineas_extra' o 'upgrade_plan'
3. 'recommendation': Una recomendación ejecutiva de 2 oraciones para el Superadmin.";

        // Diagnóstico algorítmico inteligente
        $churnRisk = 'bajo';
        $upsellOpp = 'ninguna';
        $recommendation = "El cliente mantiene una suscripción estable al {$planMeta['name']}.";

        if ($daysRemaining < 0 || $org->status === 'suspended') {
            $churnRisk = 'alto';
            $recommendation = "Cliente en suspensión por falta de pago. Se sugiere contactar directamente al tomador de decisión con una oferta de reactivación.";
        } elseif ($daysRemaining <= 5) {
            $churnRisk = 'medio';
            $recommendation = "Cuenta próxima a vencer. Emitir recordatorio con beneficio de pago anual para asegurar la retención.";
        }

        if ($usersCount >= $planMeta['included_agents']) {
            $upsellOpp = ($org->plan === 'basic') ? 'upgrade_plan' : 'agentes_extra';
            $recommendation .= " Capacidad de agentes al 100%. Oportunidad clara para ofrecer ampliación de equipo o upgrade a {$planMeta['name']}.";
        }

        return [
            'success' => true,
            'churn_risk' => $churnRisk,
            'upsell_opportunity' => $upsellOpp,
            'recommendation' => $recommendation,
        ];
    }
}
