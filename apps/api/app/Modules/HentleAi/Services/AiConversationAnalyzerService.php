<?php

namespace App\Modules\HentleAi\Services;

use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Notifications\Models\AppNotification;
use App\Modules\Notifications\Services\NotificationService;
use App\Modules\Parameters\Models\CustomStatus;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiConversationAnalyzerService
{
    public function __construct(
        protected NotificationService $notificationService,
    ) {}

    /**
     * Analiza el historial reciente de la conversación y devuelve:
     * - Recomendación de estado sugerido
     * - Recomendación de etiquetas sugeridas
     * - Extracción de entidades para ficha de contacto
     * - Radar de sentimiento y alerta de frustración
     */
    public function analyze(Conversation $conversation): array
    {
        $messages = $conversation->messages()
            ->latest('created_at')
            ->take(15)
            ->get()
            ->reverse()
            ->values();

        $inboundMessages = $messages->where('direction', 'inbound');
        $inboundTexts = $inboundMessages->pluck('body')->all();
        $fullInboundText = implode("\n", $inboundTexts);

        $contact = $conversation->contact;

        // 1. Recomendación de Estado (Fase 3)
        $statusRec = $this->analyzeStatusRecommendation($conversation, $fullInboundText);

        // 2. Recomendación de Etiquetas (Fase 3)
        $tagRecs = $this->analyzeTagRecommendations($conversation, $fullInboundText);

        // 3. Extracción de Entidades para Contacto (Fase 4)
        $extractedData = $this->extractContactEntities($contact, $fullInboundText);

        // 4. Radar de Sentimiento y Frustración (Fase 5)
        $sentiment = $this->evaluateSentimentAndFrustration($fullInboundText, $inboundMessages->count());

        return [
            'status_recommendation' => $statusRec,
            'tag_recommendations' => $tagRecs,
            'extracted_contact_data' => $extractedData,
            'sentiment' => $sentiment,
        ];
    }

    /**
     * Evalúa sugerencias de cambio de estado basadas en intención del cliente.
     */
    public function analyzeStatusRecommendation(Conversation $conversation, string $text): ?array
    {
        if (blank($text)) {
            return null;
        }

        // Detección de Desinterés / Rechazo de propuesta
        $disinterestPattern = '/\b(no\s+me\s+interesa|ya\s+no(\s+gracias)?|no\s+estoy\s+interesado|muy\s+caro|ya\s+me\s+inscrib[ií]|ya\s+eleg[ií]\s+otra|cancelar|dar\s+de\s+baja|no\s+quiero\s+saber|no\s+gracias|rechazo|desistir)\b/i';
        if (preg_match($disinterestPattern, $text)) {
            $customStatus = CustomStatus::where('organization_id', $conversation->organization_id)
                ->where(function ($q) {
                    $q->where('slug', 'like', '%no-interes%')
                      ->orWhere('slug', 'like', '%perdid%')
                      ->orWhere('slug', 'like', '%descart%')
                      ->orWhere('slug', 'like', '%rechaz%')
                      ->orWhere('name', 'like', '%No Interes%');
                })
                ->first();

            return [
                'suggested_status' => 'no_interested',
                'suggested_custom_status_id' => $customStatus?->id,
                'suggested_custom_status_name' => $customStatus?->name ?? 'No Interesado',
                'reason' => 'El cliente manifestó falta de interés o desistimiento en el chat.',
                'confidence' => 0.92,
            ];
        }

        // Detección de Alta Intención de Compra / Matrícula
        $leadPattern = '/\b(quiero\s+inscribirme|d[oó]nde\s+pago|c[oó]mo\s+me\s+matriculo|enviar\s+cotizaci[oó]n|me\s+interesa\s+mucho|cu[aá]ndo\s+empiezan|quiero\s+postular|proceder\s+con\s+el\s+pago)\b/i';
        if (preg_match($leadPattern, $text)) {
            $customStatus = CustomStatus::where('organization_id', $conversation->organization_id)
                ->where(function ($q) {
                    $q->where('slug', 'like', '%interes%')
                      ->orWhere('slug', 'like', '%cotiza%')
                      ->orWhere('slug', 'like', '%seguim%')
                      ->orWhere('name', 'like', '%Interes%');
                })
                ->first();

            return [
                'suggested_status' => 'lead',
                'suggested_custom_status_id' => $customStatus?->id,
                'suggested_custom_status_name' => $customStatus?->name ?? 'Interesado',
                'reason' => 'El cliente expresó intención de postulación o trámite.',
                'confidence' => 0.88,
            ];
        }

        return null;
    }

    /**
     * Evalúa sugerencias de etiquetas basadas en temas y facultades detectadas.
     */
    public function analyzeTagRecommendations(Conversation $conversation, string $text): array
    {
        if (blank($text)) {
            return [];
        }

        $candidates = [];

        if (preg_match('/\b(medicina|m[eé]dico|salud|doctor)\b/i', $text)) {
            $candidates[] = [
                'name' => 'Medicina',
                'color' => '#0284c7',
                'reason' => 'Mencionó carrera médica o área de salud',
            ];
        }

        if (preg_match('/\b(odontolog[ií]a|dental|dentista)\b/i', $text)) {
            $candidates[] = [
                'name' => 'Odontología',
                'color' => '#0d9488',
                'reason' => 'Mencionó carrera de odontología',
            ];
        }

        if (preg_match('/\b(enfermer[ií]a|enfermer[oa])\b/i', $text)) {
            $candidates[] = [
                'name' => 'Enfermería',
                'color' => '#10b981',
                'reason' => 'Mencionó carrera de enfermería',
            ];
        }

        if (preg_match('/\b(precio|costo|arancel|cuota|mensualidad|cotizaci[oó]n|descuento|beca|cu[aá]nto\s+vale)\b/i', $text)) {
            $candidates[] = [
                'name' => 'Cotización Solicitada',
                'color' => '#f59e0b',
                'reason' => 'Consultó aranceles, mensualidades o becas',
            ];
        }

        if (preg_match('/\b(cochabamba|cbba)\b/i', $text)) {
            $candidates[] = [
                'name' => 'Sede Cochabamba',
                'color' => '#8b5cf6',
                'reason' => 'Indicó ubicación o preferencia en Cochabamba',
            ];
        }

        if (preg_match('/\b(la\s+paz)\b/i', $text)) {
            $candidates[] = [
                'name' => 'Sede La Paz',
                'color' => '#ec4899',
                'reason' => 'Indicó ubicación o preferencia en La Paz',
            ];
        }

        if (preg_match('/\b(santa\s+cruz)\b/i', $text)) {
            $candidates[] = [
                'name' => 'Sede Santa Cruz',
                'color' => '#14b8a6',
                'reason' => 'Indicó ubicación o preferencia en Santa Cruz',
            ];
        }

        if (preg_match('/\b(reclamo|queja|mal\s+servicio|inconformidad)\b/i', $text)) {
            $candidates[] = [
                'name' => 'Reclamo',
                'color' => '#ef4444',
                'reason' => 'Expresó inconformidad o queja',
            ];
        }

        if (preg_match('/\b(no\s+interesado|desiste|rechaza)\b/i', $text)) {
            $candidates[] = [
                'name' => 'No Interesado',
                'color' => '#64748b',
                'reason' => 'Rechazó la propuesta',
            ];
        }

        // Filtrar etiquetas que el contacto ya tenga asignadas
        $existingTagNames = $conversation->contact
            ? $conversation->contact->tags()->pluck('name')->map(fn ($n) => mb_strtolower($n))->all()
            : [];

        $filtered = [];
        foreach ($candidates as $cand) {
            if (! in_array(mb_strtolower($cand['name']), $existingTagNames, true)) {
                $filtered[] = $cand;
            }
        }

        return $filtered;
    }

    /**
     * Extrae entidades como CI, email, nombre y ciudad desde los mensajes entrantes.
     */
    public function extractContactEntities(?Contact $contact, string $text): array
    {
        if (blank($text)) {
            return [
                'first_name' => null,
                'email' => null,
                'ci' => null,
                'city' => null,
                'alternative_phone' => null,
                'has_new_data' => false,
            ];
        }

        $detectedCi = null;
        $detectedEmail = null;
        $detectedName = null;
        $detectedCity = null;
        $detectedAltPhone = null;

        // 1. Cédula de Identidad (CI / Carnet)
        if (preg_match('/(?:c\.?i\.?|carnet|c[eé]dula|identidad)\s*(?:es|:|\#)?\s*([0-9]{5,9}(?:\s*[-]?\s*[a-zA-Z]{1,3})?)/iu', $text, $matches)) {
            $detectedCi = trim($matches[1]);
        }

        // 2. Correo electrónico
        if (preg_match('/[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/', $text, $matches)) {
            $detectedEmail = trim($matches[0]);
        }

        // 3. Nombre del prospecto
        if (preg_match('/(?:mi\s+nombre\s+es|me\s+llamo|soy)\s+([A-ZÁÉÍÓÚÑa-záéíóúñ]+(?:\s+[A-ZÁÉÍÓÚÑa-záéíóúñ]+){0,3})/i', $text, $matches)) {
            $nameCandidate = trim($matches[1]);
            // Excluir palabras comunes
            if (! preg_match('/^(un|una|el|la|cliente|estudiante|postulante)$/i', $nameCandidate)) {
                $detectedName = Str::title($nameCandidate);
            }
        }

        // 4. Ciudad
        if (preg_match('/\b(Cochabamba|La Paz|Santa Cruz|Sucre|Tarija|Oruro|Potos[ií]|Beni|Pando|Montero|El Alto|Quillacollo|Sacaba)\b/i', $text, $matches)) {
            $detectedCity = Str::title($matches[1]);
        }

        // 5. Teléfono alternativo
        if (preg_match('/(?:mi\s+otro\s+n[uú]mero\s+es|n[uú]mero\s+alternativo|otro\s+celular:?)\s*[:#]?\s*([+0-9\s-]{7,15})/i', $text, $matches)) {
            $cleanPhone = preg_replace('/[^0-9+]/', '', $matches[1]);
            if (strlen($cleanPhone) >= 7) {
                $detectedAltPhone = $cleanPhone;
            }
        }

        // Evaluar si aporta nuevos datos respecto al contacto actual
        $currentCi = $contact?->custom_fields['ci'] ?? $contact?->custom_fields['document_number'] ?? null;
        $currentEmail = $contact?->email;
        $currentFirstName = $contact?->first_name;
        $currentCity = $contact?->custom_fields['city'] ?? null;

        $hasNewData = false;

        if ($detectedCi && $detectedCi !== $currentCi) {
            $hasNewData = true;
        }

        if ($detectedEmail && $detectedEmail !== $currentEmail) {
            $hasNewData = true;
        }

        // Si el contacto actual tiene nombre de teléfono o genérico y se detectó un nombre real
        if ($detectedName && ($detectedName !== $currentFirstName) && (blank($currentFirstName) || preg_match('/^[0-9+]+$/', (string) $currentFirstName))) {
            $hasNewData = true;
        }

        if ($detectedCity && $detectedCity !== $currentCity) {
            $hasNewData = true;
        }

        if ($detectedAltPhone && $detectedAltPhone !== ($contact?->custom_fields['alternative_phone'] ?? null)) {
            $hasNewData = true;
        }

        return [
            'first_name' => $detectedName,
            'email' => $detectedEmail,
            'ci' => $detectedCi,
            'city' => $detectedCity,
            'alternative_phone' => $detectedAltPhone,
            'has_new_data' => $hasNewData,
        ];
    }

    /**
     * Evalúa el radar de sentimiento y nivel de frustración en tiempo real.
     */
    public function evaluateSentimentAndFrustration(string $text, int $inboundCount): array
    {
        if (blank($text)) {
            return [
                'sentiment' => 'neutral',
                'frustration_score' => 0.0,
                'is_frustrated' => false,
                'reason' => 'Sin mensajes suficientes para analizar.',
            ];
        }

        $frustrationScore = 0.0;
        $reasons = [];

        // Palabras de alta frustración
        if (preg_match('/(p[eé]sim[ao]|incompetente|estafa|engaño|burla|porquer[ií]a|manga\s+de|in[uú]tiles|verg[uü]enza)/iu', $text, $m)) {
            $frustrationScore += 0.55;
            $reasons[] = "Palabras críticas ('{$m[0]}')";
        }

        // Tiempos de espera excesivos y abandono
        if (preg_match('/(llevo\s+horas|nadie\s+atiende|nadie\s+responde|nadie\s+contesta|estoy\s+esperando\s+desde|hasta\s+cu[aá]ndo|cu[aá]nto\s+m[aá]s\s+tengo\s+que\s+esperar)/iu', $text, $m)) {
            $frustrationScore += 0.40;
            $reasons[] = "Reclamo por demora en respuesta";
        }

        // Molestia o quejas
        if (preg_match('/(falta\s+de\s+respeto|pongo\s+una\s+queja|atenci[oó]n\s+horrible|muy\s+molest[ao]|que\s+desastre)/iu', $text, $m)) {
            $frustrationScore += 0.35;
            $reasons[] = "Manifestación explícita de molestia";
        }

        // Exclamaciones e interrogaciones repetidas
        if (preg_match('/!{2,}/', $text)) {
            $frustrationScore += 0.15;
            $reasons[] = 'Signos de exclamación repetidos (!!)';
        }

        if (preg_match('/\?{2,}/', $text)) {
            $frustrationScore += 0.10;
        }

        // Indicadores positivos que reducen la frustración
        if (preg_match('/(muchas\s+gracias|excelente|agradecid[ao]|muy\s+amable|perfecto|genial|me\s+encanta)/iu', $text)) {
            $frustrationScore = max(0.0, $frustrationScore - 0.40);
        }

        $frustrationScore = min(1.0, round($frustrationScore, 2));
        $isFrustrated = $frustrationScore >= 0.70;

        $sentiment = match (true) {
            $frustrationScore >= 0.85 => 'angry',
            $frustrationScore >= 0.70 => 'frustrated',
            $frustrationScore >= 0.40 => 'hesitant',
            preg_match('/\b(gracias|excelente|perfecto|amable)\b/i', $text) => 'positive',
            default => 'neutral',
        };

        $reasonText = ! empty($reasons)
            ? implode(', ', $reasons)
            : ($sentiment === 'positive' ? 'Interacción cordial y agradecida' : 'Consulta normal sin signos de conflicto');

        return [
            'sentiment' => $sentiment,
            'frustration_score' => $frustrationScore,
            'is_frustrated' => $isFrustrated,
            'reason' => $reasonText,
        ];
    }

    /**
     * Si se detecta frustración alta, despacha una alerta proactiva a supervisores y al asesor asignado.
     */
    public function checkAndAlertFrustration(Conversation $conversation, array $analysis): ?AppNotification
    {
        $sentiment = $analysis['sentiment'] ?? [];
        if (empty($sentiment['is_frustrated'])) {
            return null;
        }

        // Evitar spam: máximo 1 alerta por conversación cada 30 minutos
        $recentAlertExists = AppNotification::where('organization_id', $conversation->organization_id)
            ->where('type', 'frustration_alert')
            ->where('action_url', 'like', "%{$conversation->id}%")
            ->where('created_at', '>=', Carbon::now()->subMinutes(30))
            ->exists();

        if ($recentAlertExists) {
            return null;
        }

        $contactName = $conversation->contact?->name ?? 'Cliente WhatsApp';

        return $this->notificationService->createNotification(
            organizationId: $conversation->organization_id,
            title: '🚨 Radar IA: Cliente con Alta Frustración',
            message: "El contacto {$contactName} presenta signos de descontento ({$sentiment['reason']}). Se sugiere atención prioritaria.",
            type: 'frustration_alert',
            userId: $conversation->assigned_to_user_id, // Si es null, llega al panel general de supervisores
            actionUrl: "/app/conversations?id={$conversation->id}",
            data: [
                'conversation_id' => $conversation->id,
                'contact_name' => $contactName,
                'frustration_score' => $sentiment['frustration_score'],
                'sentiment' => $sentiment['sentiment'],
                'reason' => $sentiment['reason'],
            ]
        );
    }
}
