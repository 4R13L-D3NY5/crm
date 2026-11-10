<?php

namespace App\Modules\Conversations\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Conversations\Models\Message;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use App\Shared\Actions\WriteAuditLogAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Shuchkin\SimpleXLSXGen;

class ConversationBulkTextActionController extends Controller
{
    /**
     * Construye la consulta de conversaciones que coinciden con el texto y dirección de mensaje.
     */
    protected function buildMatchingQuery(User $user, Request $request): Builder
    {
        $text = trim((string) $request->input('text', ''));
        $direction = (string) $request->input('direction', 'both'); // 'both', 'inbound', 'outbound'
        $whatsappAccountId = $request->input('whatsapp_account_id');
        $dateFrom = $request->input('date_from');
        $dateTo = $request->input('date_to');

        $query = Conversation::query()
            ->where('organization_id', $user->current_organization_id);

        // Control de permisos para operadores no-admin
        if (!$user->isAdmin()) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('assigned_to_user_id', $user->id)
                  ->orWhereHas('assignment', fn ($as) => $as->where('assigned_to_user_id', $user->id))
                  ->orWhereHas('whatsappAccount.authorizedUsers', function (Builder $userQuery) use ($user) {
                      $userQuery->where('users.id', $user->id)
                                ->where('whatsapp_account_users.can_view', true);
                  });
            });
        }

        if ($whatsappAccountId) {
            $query->where('whatsapp_account_id', $whatsappAccountId);
        }

        if ($text !== '') {
            $query->whereHas('messages', function (Builder $mq) use ($text, $direction, $dateFrom, $dateTo) {
                $mq->where('body', 'ilike', "%{$text}%");

                if ($direction === 'inbound') {
                    $mq->where('direction', 'inbound');
                } elseif ($direction === 'outbound') {
                    $mq->where('direction', 'outbound');
                }

                if ($dateFrom) {
                    $mq->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
                }
                if ($dateTo) {
                    $mq->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
                }
            });
        }

        return $query;
    }

    /**
     * Previsualiza cuántas conversaciones y contactos coinciden antes de ejecutar la acción masiva.
     */
    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'text' => ['required', 'string', 'min:2'],
            'direction' => ['nullable', 'string', 'in:both,inbound,outbound'],
            'whatsapp_account_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $query = $this->buildMatchingQuery($user, $request);

        $conversationsCount = (clone $query)->count();

        $contactsCount = (clone $query)
            ->whereNotNull('contact_id')
            ->distinct('contact_id')
            ->count('contact_id');

        $sample = (clone $query)
            ->with(['contact:id,first_name,last_name,phone', 'whatsappAccount:id,name,display_phone_number'])
            ->latest('last_message_at')
            ->limit(5)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'contact_name' => $c->contact ? trim("{$c->contact->first_name} {$c->contact->last_name}") : 'Sin contacto registrado',
                    'contact_phone' => $c->contact?->phone,
                    'status' => $c->status,
                    'channel' => $c->channel,
                    'account_name' => $c->whatsappAccount?->name ?? 'WhatsApp',
                ];
            });

        return response()->json([
            'data' => [
                'conversations_count' => $conversationsCount,
                'contacts_count' => $contactsCount,
                'sample' => $sample,
            ],
        ]);
    }

    /**
     * Aplica etiquetas, categorías y/o estados en lote a las conversaciones y contactos coincidentes.
     */
    public function classify(Request $request, WriteAuditLogAction $audit): JsonResponse
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'min:2'],
            'direction' => ['nullable', 'string', 'in:both,inbound,outbound'],
            'whatsapp_account_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],

            // Acciones a aplicar
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['string'],
            'new_tag_name' => ['nullable', 'string', 'max:60'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['string'],
            'status' => ['nullable', 'string', 'in:open,pending,closed'],
            'custom_status_id' => ['nullable', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $orgId = $user->current_organization_id;

        $query = $this->buildMatchingQuery($user, $request);
        $conversations = $query->with('contact')->get();

        if ($conversations->isEmpty()) {
            return response()->json([
                'success' => true,
                'message' => 'No se encontraron conversaciones que coincidan con los criterios especificados.',
                'data' => [
                    'affected_conversations' => 0,
                    'affected_contacts' => 0,
                ],
            ]);
        }

        $conversationIds = $conversations->pluck('id')->all();
        $contactIds = $conversations->pluck('contact_id')->filter()->unique()->values()->all();

        // 1. Procesar Etiquetas (Tag)
        $tagIds = $validated['tag_ids'] ?? [];
        if (!empty($validated['new_tag_name'])) {
            $tagName = trim($validated['new_tag_name']);
            $tag = Tag::firstOrCreate(
                [
                    'organization_id' => $orgId,
                    'name' => $tagName,
                ],
                [
                    'slug' => Str::slug($tagName) . '-' . Str::random(4),
                    'color_hex' => '#0d9488',
                ]
            );
            $tagIds[] = $tag->id;
        }
        $tagIds = array_values(array_unique($tagIds));

        if (!empty($tagIds) && !empty($contactIds)) {
            $now = now();
            $tagInserts = [];
            foreach ($contactIds as $contactId) {
                foreach ($tagIds as $tagId) {
                    $tagInserts[] = [
                        'contact_id' => $contactId,
                        'tag_id' => $tagId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            DB::table('contact_tag')->insertOrIgnore($tagInserts);
        }

        // 2. Procesar Categorías (Category)
        $categoryIds = array_values(array_unique($validated['category_ids'] ?? []));
        if (!empty($categoryIds)) {
            $now = now();

            // Vincular a las conversaciones
            $convCatInserts = [];
            foreach ($conversationIds as $cId) {
                foreach ($categoryIds as $catId) {
                    $convCatInserts[] = [
                        'id' => (string) Str::ulid(),
                        'organization_id' => $orgId,
                        'conversation_id' => $cId,
                        'category_id' => $catId,
                        'assigned_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            DB::table('conversation_categories')->insertOrIgnore($convCatInserts);

            // Vincular a los contactos
            if (!empty($contactIds)) {
                $contactCatInserts = [];
                foreach ($contactIds as $ctId) {
                    foreach ($categoryIds as $catId) {
                        $contactCatInserts[] = [
                            'id' => (string) Str::ulid(),
                            'organization_id' => $orgId,
                            'contact_id' => $ctId,
                            'category_id' => $catId,
                            'assigned_by_user_id' => $user->id,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                DB::table('contact_categories')->insertOrIgnore($contactCatInserts);
            }
        }

        // 3. Procesar Estado de Conversación (open, pending, closed)
        if (!empty($validated['status'])) {
            Conversation::whereIn('id', $conversationIds)->update([
                'status' => $validated['status'],
                'updated_at' => now(),
            ]);
        }

        // 4. Procesar Estado Personalizado (custom_status_id)
        if (!empty($validated['custom_status_id'])) {
            $csId = $validated['custom_status_id'];
            Conversation::whereIn('id', $conversationIds)->update([
                'custom_status_id' => $csId,
                'updated_at' => now(),
            ]);
            if (!empty($contactIds)) {
                Contact::whereIn('id', $contactIds)->update([
                    'custom_status_id' => $csId,
                    'updated_at' => now(),
                ]);
            }
        }

        // Auditoría
        $audit->execute(
            event: 'conversations.bulk_text_classify',
            user: $user,
            request: $request,
            metadata: [
                'search_text' => $validated['text'],
                'direction' => $validated['direction'] ?? 'both',
                'affected_conversations' => count($conversationIds),
                'affected_contacts' => count($contactIds),
                'tags_applied' => count($tagIds),
                'categories_applied' => count($categoryIds),
                'status_updated' => $validated['status'] ?? null,
                'custom_status_updated' => $validated['custom_status_id'] ?? null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Acción masiva completada exitosamente.',
            'data' => [
                'affected_conversations' => count($conversationIds),
                'affected_contacts' => count($contactIds),
                'tags_applied' => count($tagIds),
                'categories_applied' => count($categoryIds),
            ],
        ]);
    }

    /**
     * Exporta a formato Excel nativo (.xlsx) o CSV compatible un reporte detallado con enlaces directos al CRM.
     */
    public function export(Request $request): Response
    {
        $request->validate([
            'text' => ['required', 'string', 'min:2'],
            'direction' => ['nullable', 'string', 'in:both,inbound,outbound'],
            'whatsapp_account_id' => ['nullable', 'string'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'format' => ['nullable', 'string', 'in:xlsx,csv'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $text = trim((string) $request->input('text', ''));
        $direction = (string) $request->input('direction', 'both');
        $format = (string) $request->input('format', 'xlsx');

        $conversations = $this->buildMatchingQuery($user, $request)
            ->with([
                'contact.tags',
                'whatsappAccount:id,name,display_phone_number',
                'customStatus:id,name',
                'assignee:id,name',
                'messages' => function ($mq) use ($text, $direction) {
                    $mq->where('body', 'ilike', "%{$text}%");
                    if ($direction === 'inbound') {
                        $mq->where('direction', 'inbound');
                    } elseif ($direction === 'outbound') {
                        $mq->where('direction', 'outbound');
                    }
                    $mq->latest('created_at')->limit(1);
                },
            ])
            ->latest('last_message_at')
            ->get();

        // Determinar origen del frontend para el link directo al CRM
        $origin = $request->header('origin');
        if (!$origin && $request->header('referer')) {
            $parsed = parse_url($request->header('referer'));
            $origin = ($parsed['scheme'] ?? 'http') . '://' . ($parsed['host'] ?? 'localhost') . (isset($parsed['port']) ? ':' . $parsed['port'] : '');
        }
        if (!$origin) {
            $origin = config('app.frontend_url', 'http://localhost:9010');
        }
        $origin = rtrim($origin, '/');

        $safeTerm = preg_replace('/[^a-zA-Z0-9_-]/', '_', $text);
        $timestamp = now()->format('Ymd_His');

        // Mapeo común de filas
        $dataRows = [];
        foreach ($conversations as $conv) {
            $contact = $conv->contact;
            $matchingMsg = $conv->messages->first();
            $crmLink = "{$origin}/app/conversations?id={$conv->id}";

            $tagsList = $contact && $contact->tags->isNotEmpty()
                ? $contact->tags->pluck('name')->implode(', ')
                : 'Sin etiquetas';

            $ticketStatus = match ($conv->status) {
                'open' => 'Atendiendo',
                'pending' => 'En espera',
                'closed' => 'Cerrado',
                default => $conv->status,
            };

            $msgDirection = match ($matchingMsg?->direction) {
                'inbound' => 'Ingreso (Cliente)',
                'outbound' => 'Egreso (Asesor/Bot)',
                default => 'No especificado',
            };

            $dataRows[] = [
                'id' => $conv->id,
                'link' => $crmLink,
                'contact_name' => $contact ? trim("{$contact->first_name} {$contact->last_name}") : 'Sin contacto registrado',
                'phone' => $contact?->phone ?? 'Sin teléfono',
                'channel' => strtoupper($conv->channel),
                'account' => $conv->whatsappAccount?->name ?? $conv->whatsappAccount?->display_phone_number ?? 'Línea WhatsApp',
                'status' => $ticketStatus,
                'custom_status' => $conv->customStatus?->name ?? 'Sin estado personalizado',
                'assignee' => $conv->assignee?->name ?? 'Sin asignar',
                'tags' => $tagsList,
                'date' => $conv->last_message_at ? Carbon::parse($conv->last_message_at)->format('Y-m-d H:i:s') : $conv->created_at->format('Y-m-d H:i:s'),
                'msg_direction' => $msgDirection,
                'msg_body' => $matchingMsg?->body ?? '',
            ];
        }

        // Si se solicita XLSX y SimpleXLSXGen está disponible
        if ($format === 'xlsx' && class_exists(SimpleXLSXGen::class)) {
            $xlsxRows = [
                [
                    '<b>ID Conversación</b>',
                    '<b>Enlace CRM (Abrir Chat)</b>',
                    '<b>Contacto</b>',
                    '<b>Teléfono / WhatsApp</b>',
                    '<b>Canal</b>',
                    '<b>Línea / Cuenta Entrada</b>',
                    '<b>Estado Ticket</b>',
                    '<b>Estado Personalizado</b>',
                    '<b>Operador Asignado</b>',
                    '<b>Etiquetas Contacto</b>',
                    '<b>Fecha Última Interacción</b>',
                    '<b>Dirección Coincidencia</b>',
                    '<b>Mensaje Coincidente Detectado</b>',
                ],
            ];

            foreach ($dataRows as $r) {
                $xlsxRows[] = [
                    $r['id'],
                    '<a href="' . $r['link'] . '">Abrir Chat en CRM</a>',
                    $r['contact_name'],
                    $r['phone'],
                    $r['channel'],
                    $r['account'],
                    $r['status'],
                    $r['custom_status'],
                    $r['assignee'],
                    $r['tags'],
                    $r['date'],
                    $r['msg_direction'],
                    $r['msg_body'],
                ];
            }

            $filename = "Reporte_Chats_{$safeTerm}_{$timestamp}.xlsx";
            $xlsx = SimpleXLSXGen::fromArray($xlsxRows)
                ->setTitle('Reporte de Chats Coincidentes')
                ->autoFilter('A1:M1');

            return response((string) $xlsx, 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}\"",
                'Cache-Control' => 'max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        // Fallback a CSV con delimitador punto y coma (;) y directiva sep=; para Excel regional
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, "\xEF\xBB\xBFsep=;\r\n"); // BOM UTF-8 y forzar punto y coma en Excel

        fputcsv($handle, [
            'ID Conversación',
            'Enlace CRM (Abrir Chat)',
            'Contacto',
            'Teléfono / WhatsApp',
            'Canal',
            'Línea / Cuenta Entrada',
            'Estado Ticket',
            'Estado Personalizado',
            'Operador Asignado',
            'Etiquetas Contacto',
            'Fecha Última Interacción',
            'Dirección Coincidencia',
            'Mensaje Coincidente Detectado',
        ], ';');

        foreach ($dataRows as $r) {
            fputcsv($handle, [
                $r['id'],
                $r['link'],
                $r['contact_name'],
                $r['phone'],
                $r['channel'],
                $r['account'],
                $r['status'],
                $r['custom_status'],
                $r['assignee'],
                $r['tags'],
                $r['date'],
                $r['msg_direction'],
                $r['msg_body'],
            ], ';');
        }

        rewind($handle);
        $csvContent = stream_get_contents($handle);
        fclose($handle);

        $filename = "Reporte_Chats_{$safeTerm}_{$timestamp}.csv";

        return response($csvContent, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}
