<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Services\SuperAdminBillingService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminTenantController extends Controller
{
    public function __construct(
        protected SuperAdminBillingService $billingService,
    ) {}

    /**
     * Lista todos los tenants y el resumen financiero del SaaS.
     */
    public function index(): JsonResponse
    {
        $data = $this->billingService->getTenantsWithMetrics();
        return response()->json($data);
    }

    /**
     * Renueva la suscripción de un cliente por N meses.
     */
    public function renew(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $months = (int) $request->input('months', 1);
        $method = $request->input('payment_method', 'transfer');
        $ref = $request->input('reference_code');
        $notes = $request->input('notes');

        $result = $this->billingService->renewSubscription($org, $months, $method, $ref, $notes);
        return response()->json($result);
    }

    /**
     * Previsualiza la matemática de prorrateo antes de aplicar un cambio de plan.
     */
    public function previewProration(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $newPlan = $request->input('new_plan', 'pro');
        $extraPhones = (int) $request->input('extra_phones', 0);
        $extraAgents = (int) $request->input('extra_agents', 0);
        $extraSocial = (int) $request->input('extra_social', 0);

        $proration = $this->billingService->calculateProration($org, $newPlan, $extraPhones, $extraAgents, $extraSocial);
        return response()->json($proration);
    }

    /**
     * Aplica el cambio de plan y cobra el ajuste prorrateado.
     */
    public function changePlan(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $newPlan = $request->input('new_plan', 'pro');
        $extraPhones = (int) $request->input('extra_phones', 0);
        $extraAgents = (int) $request->input('extra_agents', 0);
        $extraSocial = (int) $request->input('extra_social', 0);
        $method = $request->input('payment_method', 'transfer');
        $ref = $request->input('reference_code');

        $result = $this->billingService->applyPlanChange($org, $newPlan, $extraPhones, $extraAgents, $extraSocial, $method, $ref);
        return response()->json($result);
    }

    /**
     * Ajusta las cuotas adicionales de canales WhatsApp y agentes con prorrateo exacto.
     */
    public function adjustQuotas(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $extraPhones = (int) $request->input('extra_phones', $org->extra_whatsapp_lines ?? 0);
        $extraAgents = (int) $request->input('extra_agents', $org->extra_agents ?? 0);
        $extraSocial = (int) $request->input('extra_social', $org->extra_social_accounts ?? 0);
        $method = $request->input('payment_method', 'transfer');
        $ref = $request->input('reference_code');
        $notes = $request->input('notes');

        $result = $this->billingService->adjustQuotas($org, $extraPhones, $extraAgents, $extraSocial, $method, $ref, $notes);
        return response()->json($result);
    }

    /**
     * Alterna la suspensión del servicio para un cliente.
     */
    public function toggleSuspension(string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $newStatus = $this->billingService->toggleSuspension($org);
        return response()->json([
            'success' => true,
            'status' => $newStatus,
            'message' => $newStatus === 'suspended' ? 'Servicio suspendido' : 'Servicio reactivado',
        ]);
    }

    /**
     * Genera un nuevo Token de Licencia criptográfico firmado.
     */
    public function generateToken(string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $expiresAt = $org->expires_at ?? now()->addMonth();
        $token = $this->billingService->generateSignedLicenseToken($org->id, $org->plan, $expiresAt);
        $org->update(['license_token' => $token]);

        return response()->json([
            'success' => true,
            'license_token' => $token,
        ]);
    }

    /**
     * Genera un recordatorio inteligente de cobro por WhatsApp usando Hentle-AI.
     */
    public function aiReminder(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $tone = $request->input('tone', 'friendly');

        $reminder = $this->billingService->generateAiPaymentReminder($org, $tone);
        return response()->json($reminder);
    }

    /**
     * Genera una auditoría comercial de salud de cuenta usando Hentle-AI.
     */
    public function aiAudit(string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $audit = $this->billingService->generateAiHealthAudit($org);
        return response()->json($audit);
    }

    /**
     * Amplía o reconfigura el periodo de prueba de una empresa cliente.
     */
    public function extendTrial(Request $request, string $id): JsonResponse
    {
        $org = Organization::findOrFail($id);
        $days = (int) $request->input('days', 0);
        $plan = $request->input('plan');
        $extraPhones = $request->input('extra_phones');
        $extraAgents = $request->input('extra_agents');
        $extraSocial = $request->input('extra_social');
        $mode = $request->input('mode'); // 'active' | 'official' | 'trial'
        $startsAt = $request->input('starts_at'); // e.g. '2026-11-03'
        $exactDate = $request->input('exact_date') ?? $request->input('expires_at'); // e.g. '2026-12-15'

        $now = now();

        // 1. Fecha de inicio (si se pasa)
        if ($request->has('starts_at')) {
            $parsedStart = $startsAt ? Carbon::parse($startsAt)->startOfDay() : null;
        } else {
            $parsedStart = $org->starts_at;
        }

        // 2. Base para cálculo de días
        if ($parsedStart && $parsedStart->isFuture()) {
            $baseDate = $parsedStart->copy();
        } elseif ($org->expires_at && $org->expires_at->isFuture()) {
            $baseDate = $org->expires_at->copy();
        } else {
            $baseDate = $now->copy();
        }

        // 3. Fecha de vencimiento exacta (siempre a las 23:59:59)
        if (! empty($exactDate)) {
            $newExpires = Carbon::parse($exactDate)->setTime(23, 59, 59);
        } else {
            $daysToAdd = $days > 0 ? $days : 7;
            $newExpires = $baseDate->copy()->addDays($daysToAdd)->setTime(23, 59, 59);
        }

        // 4. Modalidad (Oficial vs Prueba)
        if ($mode === 'official' || $mode === 'active') {
            $newStatus = 'active';
        } elseif ($mode === 'trial') {
            $newStatus = 'trial';
        } else {
            $newStatus = $org->status ?: 'active';
        }

        $updateData = [
            'status' => $newStatus,
            'expires_at' => $newExpires,
        ];

        if ($request->has('starts_at')) {
            $updateData['starts_at'] = $parsedStart;
        }

        $phonesExtra = $extraPhones !== null ? (int) $extraPhones : ($org->extra_whatsapp_lines ?? 0);
        $socialExtra = $extraSocial !== null ? (int) $extraSocial : ($org->extra_social_accounts ?? 0);
        $agentsExtra = $extraAgents !== null ? (int) $extraAgents : ($org->extra_agents ?? 0);

        if ($extraPhones !== null) {
            $updateData['extra_whatsapp_lines'] = $phonesExtra;
        }

        if ($extraSocial !== null) {
            $updateData['extra_social_accounts'] = $socialExtra;
        }

        if ($extraAgents !== null) {
            $updateData['extra_agents'] = $agentsExtra;
        }

        $activePlan = $plan ?: $org->plan ?: 'elite';
        $updateData['plan'] = $activePlan;
        $pricing = $this->billingService->getPlanPricing($activePlan);
        $updateData['max_whatsapp_lines'] = $pricing['included_phones'] + $phonesExtra;
        $updateData['max_social_accounts'] = ($pricing['included_social'] ?? 8) + $socialExtra;
        $updateData['max_agents'] = $pricing['included_agents'] + $agentsExtra;
        
        $baseMonthly = $pricing['base_monthly'];
        $phonesCost = $phonesExtra * $pricing['extra_phone_rate'];
        $socialCost = $socialExtra * ($pricing['extra_social_rate'] ?? 10.00);
        $agentsCost = $agentsExtra * $pricing['extra_agent_rate'];
        $updateData['monthly_price_usd'] = round($baseMonthly + $phonesCost + $socialCost + $agentsCost, 2);

        $licenseToken = $this->billingService->generateSignedLicenseToken($org->id, $activePlan, $newExpires);
        $updateData['license_token'] = $licenseToken;

        $org->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Licencia para {$org->name} actualizada exitosamente.",
            'new_expires_at' => $newExpires->format('d/m/Y 23:59:59'),
            'expires_at_date' => $newExpires->format('Y-m-d'),
            'days_remaining' => (int) now()->diffInDays($newExpires, false),
            'status' => $org->status,
            'modalidad' => $org->status === 'active' ? 'official' : 'trial',
            'plan' => $org->plan,
            'starts_at' => $org->starts_at?->format('Y-m-d'),
        ]);
    }
}
