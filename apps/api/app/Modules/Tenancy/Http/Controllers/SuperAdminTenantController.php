<?php

namespace App\Modules\Tenancy\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenancy\Models\Organization;
use App\Modules\Tenancy\Services\SuperAdminBillingService;
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

        $proration = $this->billingService->calculateProration($org, $newPlan, $extraPhones, $extraAgents);
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
        $method = $request->input('payment_method', 'transfer');
        $ref = $request->input('reference_code');

        $result = $this->billingService->applyPlanChange($org, $newPlan, $extraPhones, $extraAgents, $method, $ref);
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
        $method = $request->input('payment_method', 'transfer');
        $ref = $request->input('reference_code');
        $notes = $request->input('notes');

        $result = $this->billingService->adjustQuotas($org, $extraPhones, $extraAgents, $method, $ref, $notes);
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
        $days = (int) $request->input('days', 7);
        $plan = $request->input('plan');
        $extraPhones = $request->input('extra_phones');
        $extraAgents = $request->input('extra_agents');

        $baseDate = ($org->expires_at && $org->expires_at->isFuture())
            ? $org->expires_at
            : now();

        $newExpires = $baseDate->copy()->addDays($days);

        $updateData = [
            'status' => 'trial',
            'expires_at' => $newExpires,
        ];

        if ($plan) {
            $updateData['plan'] = $plan;
            $pricing = $this->billingService->getPlanPricing($plan);
            $updateData['max_whatsapp_lines'] = $pricing['included_phones'];
            $updateData['max_agents'] = $pricing['included_agents'];
            $updateData['monthly_price_usd'] = $pricing['base_monthly'];
        }

        if ($extraPhones !== null) {
            $updateData['extra_whatsapp_lines'] = (int) $extraPhones;
        }

        if ($extraAgents !== null) {
            $updateData['extra_agents'] = (int) $extraAgents;
        }

        $org->update($updateData);

        return response()->json([
            'success' => true,
            'message' => "Periodo de prueba para {$org->name} actualizado a {$days} días adicionales.",
            'new_expires_at' => $newExpires->format('Y-m-d H:i:s'),
            'days_remaining' => (int) now()->diffInDays($newExpires, false),
            'status' => $org->status,
            'plan' => $org->plan,
        ]);
    }
}
