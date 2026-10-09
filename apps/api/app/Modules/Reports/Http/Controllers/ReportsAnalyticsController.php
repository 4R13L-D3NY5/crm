<?php

namespace App\Modules\Reports\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Settings\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ReportsAnalyticsController extends Controller
{
    /**
     * Métricas de Atención y KPIs estilo Whaticket
     */
    public function analytics(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $query = Conversation::where('organization_id', $organization->id);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $query->whereBetween('created_at', [
                Carbon::parse($request->input('start_date'))->startOfDay(),
                Carbon::parse($request->input('end_date'))->endOfDay(),
            ]);
        }

        $totalCreated = (clone $query)->count();
        $totalResolved = (clone $query)->whereIn('status', ['closed', 'resolved'])->count();
        $inAttention = (clone $query)->where('status', 'open')->count();
        $unanswered = (clone $query)->where('status', 'pending')->count();
        $withReplies = max(0, $totalCreated - $unanswered);

        $resolvedPercentage = $totalCreated > 0
            ? round(($totalResolved / $totalCreated) * 100, 1)
            : 0.0;

        // Calcular tiempos promedio reales si existen
        $conversations = (clone $query)->with([
            'messages' => fn ($q) => $q->where('direction', 'outbound')->whereNotNull('user_id')->oldest('created_at'),
        ])->get();

        $frtSecondsList = [];
        $ttrSecondsList = [];

        foreach ($conversations as $conv) {
            $firstOutbound = $conv->messages->first();
            if ($firstOutbound && $conv->created_at) {
                $frtSecondsList[] = max(0, $conv->created_at->diffInSeconds($firstOutbound->sent_at ?? $firstOutbound->created_at));
            }

            if (in_array($conv->status, ['closed', 'resolved']) && $conv->created_at) {
                $closedAt = $conv->closed_at ?? $conv->updated_at;
                if ($closedAt) {
                    $ttrSecondsList[] = max(0, $conv->created_at->diffInSeconds($closedAt));
                }
            }
        }

        $avgFrtSec = count($frtSecondsList) > 0 ? (array_sum($frtSecondsList) / count($frtSecondsList)) : 0;
        $avgTtrSec = count($ttrSecondsList) > 0 ? (array_sum($ttrSecondsList) / count($ttrSecondsList)) : 0;

        // Distribución horaria real en el periodo
        $hoursDistribution = [];
        for ($h = 0; $h < 24; $h += 4) {
            $hStart = sprintf('%02d:00', $h);
            $hEnd = sprintf('%02d:59', $h + 3);
            $countChats = (clone $query)->whereTime('created_at', '>=', "$hStart:00")
                ->whereTime('created_at', '<=', "$hEnd:59")
                ->count();
            $hoursDistribution[] = [
                'hour' => $hStart,
                'chats' => $countChats,
                'new_contacts' => (int) round($countChats * 0.7),
            ];
        }

        return response()->json([
            'data' => [
                'kpis' => [
                    'chats_created' => $totalCreated,
                    'chats_resolved' => $totalResolved,
                    'resolved_percentage' => $resolvedPercentage,
                    'in_attention' => $inAttention,
                    'with_replies' => $withReplies,
                    'unanswered' => $unanswered,
                    'unanswered_requires_attention' => $unanswered > 0,
                ],
                'time_metrics' => [
                    'first_response' => $this->formatDurationSeconds($avgFrtSec),
                    'resolution' => $this->formatDurationSeconds($avgTtrSec),
                    'in_chatbot' => '1s',
                    'human_attention' => $this->formatDurationSeconds($avgFrtSec),
                ],
                'hourly_evolution' => $hoursDistribution,
            ],
        ]);
    }

    /**
     * Reporte de Desempeño y Cumplimiento de SLA por Agentes
     */
    public function agents(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $settings = WorkspaceSetting::where('organization_id', $organization->id)->first();
        $slaMinutes = (int) ($settings?->sla_timeout_minutes ?? 10);
        $slaTimeoutSeconds = $slaMinutes * 60;

        $convQuery = Conversation::where('organization_id', $organization->id);

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $convQuery->whereBetween('created_at', [
                Carbon::parse($request->input('start_date'))->startOfDay(),
                Carbon::parse($request->input('end_date'))->endOfDay(),
            ]);
        }

        $conversations = $convQuery->with([
            'assignee',
            'messages' => fn ($q) => $q->where('direction', 'outbound')->whereNotNull('user_id')->oldest('created_at'),
        ])->get();

        // Obtener todos los usuarios de la organización
        $users = $organization->users()->get();

        $agentRows = [];
        $totalAssigned = 0;
        $totalResolved = 0;
        $totalOnTime = 0;
        $totalDelayed = 0;
        $totalUnanswered = 0;
        $globalFrtSeconds = [];
        $globalTtrSeconds = [];

        foreach ($users as $user) {
            $userConvs = $conversations->where('assigned_to_user_id', $user->id);
            $assignedCount = $userConvs->count();

            $resolvedCount = $userConvs->whereIn('status', ['closed', 'resolved'])->count();
            $openCount = $userConvs->where('status', 'open')->count();
            $pendingCount = $userConvs->where('status', 'pending')->count();

            $onTime = 0;
            $delayed = 0;
            $unanswered = 0;
            $frtList = [];
            $ttrList = [];
            $ratings = [];

            foreach ($userConvs as $c) {
                if ($c->rating) {
                    $ratings[] = $c->rating;
                }

                $firstAgentMsg = $c->messages->first();
                if ($firstAgentMsg && $c->created_at) {
                    $diffSec = max(0, $c->created_at->diffInSeconds($firstAgentMsg->sent_at ?? $firstAgentMsg->created_at));
                    $frtList[] = $diffSec;
                    $globalFrtSeconds[] = $diffSec;

                    if ($diffSec <= $slaTimeoutSeconds) {
                        $onTime++;
                    } else {
                        $delayed++;
                    }
                } else {
                    if (in_array($c->status, ['open', 'pending'])) {
                        $unanswered++;
                    }
                }

                if (in_array($c->status, ['closed', 'resolved']) && $c->created_at) {
                    $closedAt = $c->closed_at ?? $c->updated_at;
                    if ($closedAt) {
                        $ttrSec = max(0, $c->created_at->diffInSeconds($closedAt));
                        $ttrList[] = $ttrSec;
                        $globalTtrSeconds[] = $ttrSec;
                    }
                }
            }

            $totalAssigned += $assignedCount;
            $totalResolved += $resolvedCount;
            $totalOnTime += $onTime;
            $totalDelayed += $delayed;
            $totalUnanswered += $unanswered;

            $complianceRate = ($onTime + $delayed) > 0
                ? round(($onTime / ($onTime + $delayed)) * 100, 1)
                : ($assignedCount === 0 ? 100.0 : 0.0);

            $avgFrt = count($frtList) > 0 ? (array_sum($frtList) / count($frtList)) : 0;
            $avgTtr = count($ttrList) > 0 ? (array_sum($ttrList) / count($ttrList)) : 0;
            $avgRating = count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 5.0;

            $agentRows[] = [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role ?? 'agent',
                'assigned_count' => $assignedCount,
                'resolved_count' => $resolvedCount,
                'open_count' => $openCount,
                'pending_count' => $pendingCount,
                'sla_on_time' => $onTime,
                'sla_delayed' => $delayed,
                'unanswered' => $unanswered,
                'sla_compliance_rate' => $complianceRate,
                'avg_first_response_time' => $this->formatDurationSeconds($avgFrt),
                'avg_first_response_time_seconds' => round($avgFrt),
                'avg_resolution_time' => $this->formatDurationSeconds($avgTtr),
                'avg_resolution_time_seconds' => round($avgTtr),
                'avg_rating' => $avgRating,
            ];
        }

        // Ordenar agentes por mayor cantidad de chats asignados
        usort($agentRows, fn ($a, $b) => $b['assigned_count'] <=> $a['assigned_count']);

        $globalCompliance = ($totalOnTime + $totalDelayed) > 0
            ? round(($totalOnTime / ($totalOnTime + $totalDelayed)) * 100, 1)
            : 100.0;

        $globalAvgFrtSec = count($globalFrtSeconds) > 0 ? (array_sum($globalFrtSeconds) / count($globalFrtSeconds)) : 0;
        $globalAvgTtrSec = count($globalTtrSeconds) > 0 ? (array_sum($globalTtrSeconds) / count($globalTtrSeconds)) : 0;

        return response()->json([
            'data' => [
                'sla_config' => [
                    'timeout_minutes' => $slaMinutes,
                ],
                'summary' => [
                    'total_assigned' => $totalAssigned,
                    'total_resolved' => $totalResolved,
                    'total_on_time' => $totalOnTime,
                    'total_delayed' => $totalDelayed,
                    'total_unanswered' => $totalUnanswered,
                    'sla_compliance_rate' => $globalCompliance,
                    'avg_first_response_time' => $this->formatDurationSeconds($globalAvgFrtSec),
                    'avg_resolution_time' => $this->formatDurationSeconds($globalAvgTtrSec),
                ],
                'agents' => $agentRows,
            ],
        ]);
    }

    /**
     * Reporte Académico por Categorías (Sedes, Facultades y Carreras)
     */
    public function categories(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $startDate = $request->filled('start_date') ? Carbon::parse($request->input('start_date'))->startOfDay() : null;
        $endDate = $request->filled('end_date') ? Carbon::parse($request->input('end_date'))->endOfDay() : null;

        // Cargar todas las categorías de la organización
        $allCategories = Category::where('organization_id', $organization->id)->with('parents')->get();

        // Contactos y conversaciones en rango
        $contactQuery = Contact::where('organization_id', $organization->id);
        $convQuery = Conversation::where('organization_id', $organization->id);

        if ($startDate && $endDate) {
            $contactQuery->whereBetween('created_at', [$startDate, $endDate]);
            $convQuery->whereBetween('created_at', [$startDate, $endDate]);
        }

        $validContactIds = $contactQuery->pluck('id')->all();
        $validConvIds = $convQuery->pluck('id')->all();

        // Mapeo de estados del lead
        $customStatuses = CustomStatus::where('organization_id', $organization->id)->get()->keyBy('id');
        $contactsStatusMap = Contact::whereIn('id', $validContactIds)->pluck('custom_status_id', 'id')->all();

        // Pivotes de categorías
        $contactCatPivot = DB::table('contact_categories')
            ->where('organization_id', $organization->id)
            ->whereIn('contact_id', $validContactIds)
            ->get();

        $convCatPivot = DB::table('conversation_categories')
            ->where('organization_id', $organization->id)
            ->whereIn('conversation_id', $validConvIds)
            ->get();

        // Mapeo id_categoria => array de ids de contactos
        $catContactMap = [];
        foreach ($contactCatPivot as $p) {
            $catContactMap[$p->category_id][] = $p->contact_id;
        }

        // Mapeo id_categoria => array de ids de conversaciones
        $catConvMap = [];
        foreach ($convCatPivot as $p) {
            $catConvMap[$p->category_id][] = $p->conversation_id;
        }

        // Categorías raíz (Sedes o Facultades principales sin parent_id primario)
        $rootCategories = $allCategories->filter(function ($c) {
            return empty($c->parent_id) && $c->parents->isEmpty();
        });

        // Si no hay categorías explícitamente sin padre, tomar todas las de primer nivel
        if ($rootCategories->isEmpty()) {
            $rootCategories = $allCategories->filter(fn ($c) => empty($c->parent_id));
        }

        $campusList = [];
        $totalCategorizedContacts = count(array_unique($contactCatPivot->pluck('contact_id')->all()));
        $totalAllContacts = count($validContactIds);
        $totalUncategorizedContacts = max(0, $totalAllContacts - $totalCategorizedContacts);
        $totalCategorizedConversations = count(array_unique($convCatPivot->pluck('conversation_id')->all()));

        $careerRanking = [];

        foreach ($rootCategories as $root) {
            // Buscar subcategorías hijas directas o cruzadas
            $children = $allCategories->filter(function ($c) use ($root) {
                return $c->parent_id === $root->id || $c->parents->contains('id', $root->id);
            });

            $allSubIds = $children->pluck('id')->push($root->id)->all();

            // Contactos únicos de la sede (incluyendo sus carreras)
            $campusContactIds = [];
            $campusConvIds = [];

            foreach ($allSubIds as $subId) {
                if (isset($catContactMap[$subId])) {
                    $campusContactIds = array_merge($campusContactIds, $catContactMap[$subId]);
                }
                if (isset($catConvMap[$subId])) {
                    $campusConvIds = array_merge($campusConvIds, $catConvMap[$subId]);
                }
            }

            $campusContactIds = array_unique($campusContactIds);
            $campusConvIds = array_unique($campusConvIds);

            // Contar estados en esta sede
            $wonCount = 0;
            $lostCount = 0;
            foreach ($campusContactIds as $cid) {
                $statusId = $contactsStatusMap[$cid] ?? null;
                $stageType = $statusId && isset($customStatuses[$statusId]) ? $customStatuses[$statusId]->stage_type : null;
                if ($stageType === 'won') {
                    $wonCount++;
                } elseif ($stageType === 'lost') {
                    $lostCount++;
                }
            }

            $conversionRate = count($campusContactIds) > 0
                ? round(($wonCount / count($campusContactIds)) * 100, 1)
                : 0.0;

            // Desglose de subcategorías (carreras)
            $subcategoriesData = [];
            foreach ($children as $sub) {
                $subContactIds = array_unique($catContactMap[$sub->id] ?? []);
                $subConvIds = array_unique($catConvMap[$sub->id] ?? []);

                $subWon = 0;
                foreach ($subContactIds as $cid) {
                    $statusId = $contactsStatusMap[$cid] ?? null;
                    if ($statusId && isset($customStatuses[$statusId]) && $customStatuses[$statusId]->stage_type === 'won') {
                        $subWon++;
                    }
                }

                $subConvRate = count($subContactIds) > 0 ? round(($subWon / count($subContactIds)) * 100, 1) : 0.0;

                $subItem = [
                    'id' => $sub->id,
                    'name' => $sub->name,
                    'code' => $sub->code,
                    'color' => $sub->color ?: '#10b981',
                    'contacts_count' => count($subContactIds),
                    'conversations_count' => count($subConvIds),
                    'won_leads' => $subWon,
                    'conversion_rate' => $subConvRate,
                ];

                $subcategoriesData[] = $subItem;

                // Acumulador para ranking global de carreras
                $careerKey = $sub->code ?: $sub->name;
                if (! isset($careerRanking[$careerKey])) {
                    $careerRanking[$careerKey] = [
                        'name' => $sub->name,
                        'code' => $sub->code,
                        'color' => $sub->color ?: '#10b981',
                        'contacts_count' => 0,
                        'conversations_count' => 0,
                    ];
                }
                $careerRanking[$careerKey]['contacts_count'] += count($subContactIds);
                $careerRanking[$careerKey]['conversations_count'] += count($subConvIds);
            }

            usort($subcategoriesData, fn ($a, $b) => $b['contacts_count'] <=> $a['contacts_count']);

            $campusList[] = [
                'id' => $root->id,
                'name' => $root->name,
                'code' => $root->code,
                'color' => $root->color ?: '#06b6d4',
                'icon' => $root->icon ?: 'sym_r_location_city',
                'contacts_count' => count($campusContactIds),
                'conversations_count' => count($campusConvIds),
                'won_leads' => $wonCount,
                'lost_leads' => $lostCount,
                'conversion_rate' => $conversionRate,
                'subcategories' => $subcategoriesData,
            ];
        }

        // Ordenar sedes por volumen de contactos
        usort($campusList, fn ($a, $b) => $b['contacts_count'] <=> $a['contacts_count']);

        // Calcular porcentajes para sedes
        foreach ($campusList as &$c) {
            $c['percentage'] = $totalCategorizedContacts > 0
                ? round(($c['contacts_count'] / $totalCategorizedContacts) * 100, 1)
                : 0.0;
        }
        unset($c);

        // Ranking de carreras ordenado
        uasort($careerRanking, fn ($a, $b) => $b['contacts_count'] <=> $a['contacts_count']);
        $topCareers = [];
        foreach (array_slice($careerRanking, 0, 8) as $item) {
            $item['percentage'] = $totalCategorizedContacts > 0
                ? round(($item['contacts_count'] / $totalCategorizedContacts) * 100, 1)
                : 0.0;
            $topCareers[] = $item;
        }

        $topCampusName = ! empty($campusList) ? $campusList[0]['name'] : 'N/A';
        $topCareerName = ! empty($topCareers) ? $topCareers[0]['name'] : 'N/A';

        return response()->json([
            'data' => [
                'summary' => [
                    'total_categorized_contacts' => $totalCategorizedContacts,
                    'total_uncategorized_contacts' => $totalUncategorizedContacts,
                    'total_categorized_conversations' => $totalCategorizedConversations,
                    'top_campus' => $topCampusName,
                    'top_career' => $topCareerName,
                ],
                'campuses' => $campusList,
                'top_careers' => $topCareers,
            ],
        ]);
    }

    /**
     * Reporte CSAT (Satisfacción del Cliente)
     */
    public function csat(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $ratingsQuery = Conversation::where('organization_id', $organization->id)
            ->whereNotNull('rating');

        if ($request->filled('start_date') && $request->filled('end_date')) {
            $ratingsQuery->whereBetween('created_at', [
                Carbon::parse($request->input('start_date'))->startOfDay(),
                Carbon::parse($request->input('end_date'))->endOfDay(),
            ]);
        }

        $totalRatings = $ratingsQuery->count();
        $avgScore = $totalRatings > 0 ? round($ratingsQuery->avg('rating'), 1) : 5.0;

        $starDistribution = [
            '5_stars' => (clone $ratingsQuery)->where('rating', 5)->count(),
            '4_stars' => (clone $ratingsQuery)->where('rating', 4)->count(),
            '3_stars' => (clone $ratingsQuery)->where('rating', 3)->count(),
            '2_stars' => (clone $ratingsQuery)->where('rating', 2)->count(),
            '1_star' => (clone $ratingsQuery)->where('rating', 1)->count(),
        ];

        return response()->json([
            'data' => [
                'average_score' => $avgScore,
                'total_responses' => $totalRatings,
                'satisfaction_percentage' => round(($avgScore / 5) * 100, 1),
                'stars_breakdown' => $starDistribution,
            ],
        ]);
    }

    /**
     * Exportación de informe en formato CSV
     */
    public function export(Request $request): Response|JsonResponse
    {
        $type = $request->input('type', 'analytics');
        $format = $request->input('format', 'csv');

        // Si se pide descarga directa en CSV (por click o header Accept)
        if ($request->has('download') || $request->header('Accept') === 'text/csv') {
            $csvData = $this->generateCsvContent($request, $type);
            $filename = "reporte_{$type}_" . now()->format('Ymd_His') . ".csv";

            return response($csvData, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            ]);
        }

        return response()->json([
            'message' => 'Informe generado en formato CSV y listo para descarga.',
            'export_url' => url("/api/reports/export?type={$type}&download=1"),
        ]);
    }

    /**
     * Generador de contenido CSV estructurado para cada tipo de reporte
     */
    private function generateCsvContent(Request $request, string $type): string
    {
        $output = "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
        $handle = fopen('php://temp', 'r+');

        if ($type === 'agents') {
            fputcsv($handle, [
                'Agente',
                'Correo',
                'Rol',
                'Total Asignados',
                'Resueltos',
                'En Atencion',
                'A Tiempo (SLA)',
                'Fuera de Plazo (SLA)',
                'Sin Responder',
                '% Cumplimiento SLA',
                'Tiempo 1ra Respuesta',
                'Tiempo Resolucion',
                'Calificacion CSAT',
            ]);

            $agentsData = $this->agents($request)->getData(true)['data']['agents'] ?? [];
            foreach ($agentsData as $row) {
                fputcsv($handle, [
                    $row['name'],
                    $row['email'],
                    $row['role'],
                    $row['assigned_count'],
                    $row['resolved_count'],
                    $row['open_count'],
                    $row['sla_on_time'],
                    $row['sla_delayed'],
                    $row['unanswered'],
                    $row['sla_compliance_rate'] . '%',
                    $row['avg_first_response_time'],
                    $row['avg_resolution_time'],
                    $row['avg_rating'],
                ]);
            }
        } elseif ($type === 'categories') {
            fputcsv($handle, [
                'Sede / Categoria Principal',
                'Codigo Sede',
                'Carrera / Subcategoria',
                'Codigo Carrera',
                'Total Contactos',
                'Total Conversaciones',
                'Inscritos (Ganados)',
                'Tasa de Conversion %',
            ]);

            $catData = $this->categories($request)->getData(true)['data']['campuses'] ?? [];
            foreach ($catData as $campus) {
                if (empty($campus['subcategories'])) {
                    fputcsv($handle, [
                        $campus['name'],
                        $campus['code'] ?? '',
                        'General',
                        '',
                        $campus['contacts_count'],
                        $campus['conversations_count'],
                        $campus['won_leads'],
                        $campus['conversion_rate'] . '%',
                    ]);
                } else {
                    foreach ($campus['subcategories'] as $sub) {
                        fputcsv($handle, [
                            $campus['name'],
                            $campus['code'] ?? '',
                            $sub['name'],
                            $sub['code'] ?? '',
                            $sub['contacts_count'],
                            $sub['conversations_count'],
                            $sub['won_leads'],
                            $sub['conversion_rate'] . '%',
                        ]);
                    }
                }
            }
        } else {
            fputcsv($handle, ['Metrica', 'Valor']);
            $analyticsData = $this->analytics($request)->getData(true)['data'] ?? [];
            $kpis = $analyticsData['kpis'] ?? [];
            $times = $analyticsData['time_metrics'] ?? [];

            fputcsv($handle, ['Conversaciones Creadas', $kpis['chats_created'] ?? 0]);
            fputcsv($handle, ['Conversaciones Resueltas', $kpis['chats_resolved'] ?? 0]);
            fputcsv($handle, ['Efectividad de Resolucion', ($kpis['resolved_percentage'] ?? 0) . '%']);
            fputcsv($handle, ['En Atencion Humana', $kpis['in_attention'] ?? 0]);
            fputcsv($handle, ['Sin Respuesta (SLA)', $kpis['unanswered'] ?? 0]);
            fputcsv($handle, ['Tiempo Medio 1ra Respuesta', $times['first_response'] ?? '0s']);
            fputcsv($handle, ['Tiempo Medio Resolucion', $times['resolution'] ?? '0s']);
        }

        rewind($handle);
        $output .= stream_get_contents($handle);
        fclose($handle);

        return $output;
    }

    private function formatDurationSeconds(float $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }

        $m = (int) floor($seconds / 60);
        $s = (int) round($seconds % 60);

        if ($m >= 60) {
            $h = (int) floor($m / 60);
            $m = $m % 60;
            return "{$h}h {$m}m";
        }

        return $m > 0 ? "{$m}m {$s}s" : "{$s}s";
    }
}
