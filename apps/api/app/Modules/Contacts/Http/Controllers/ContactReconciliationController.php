<?php

namespace App\Modules\Contacts\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Contacts\Models\Contact;
use App\Modules\Contacts\Models\Tag;
use App\Modules\Conversations\Models\Conversation;
use App\Modules\Parameters\Models\Category;
use App\Modules\Parameters\Models\CustomStatus;
use App\Modules\Settings\Models\WorkspaceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContactReconciliationController extends Controller
{
    /**
     * Previsualizar y simular el cotejo de contactos sin alterar la base de datos.
     */
    public function preview(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rule' => ['required', 'array'],
            'rule.phone_column' => ['required', 'string'],
            'rule.name_column' => ['nullable', 'string'],
            'rule.ci_column' => ['nullable', 'string'],
            'rule.email_column' => ['nullable', 'string'],
            'rule.target_status_id' => ['required', 'string'],
            'rule.category_mode' => ['nullable', 'string', 'in:none,fixed,dynamic'],
            'rule.category_column' => ['nullable', 'string'],
            'rule.fixed_category_id' => ['nullable', 'string'],
            'rule.auto_create_unmatched' => ['nullable', 'boolean'],
            'rule.auto_tag' => ['nullable', 'string'],
        ]);

        $rule = $validated['rule'];
        $autoCreate = (bool) ($rule['auto_create_unmatched'] ?? false);

        // Cargar contexto de la organización
        $targetStatus = CustomStatus::where('organization_id', $organization->id)
            ->find($rule['target_status_id']);

        $allCategories = Category::where('organization_id', $organization->id)->get();
        $allContacts = Contact::where('organization_id', $organization->id)->get();

        $previewRows = [];
        $matchedCount = 0;
        $toCreateCount = 0;
        $unmatchedCount = 0;
        $invalidCount = 0;

        foreach ($validated['rows'] as $index => $row) {
            $rawPhone = (string) ($row[$rule['phone_column']] ?? '');
            $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
            $name = trim((string) ($row[$rule['name_column'] ?? ''] ?? ''));
            $ci = trim((string) ($row[$rule['ci_column'] ?? ''] ?? ''));
            $email = trim((string) ($row[$rule['email_column'] ?? ''] ?? ''));

            if (empty($cleanPhone) && empty($email) && empty($ci)) {
                $invalidCount++;
                $previewRows[] = [
                    'row_index' => $index + 1,
                    'input_name' => $name,
                    'input_phone' => $rawPhone,
                    'input_ci' => $ci,
                    'input_email' => $email,
                    'status' => 'invalid',
                    'action' => 'Omitido (sin datos de contacto)',
                    'matched_contact' => null,
                    'target_status' => $targetStatus?->name ?? 'N/A',
                    'target_category' => null,
                ];
                continue;
            }

            // Buscar coincidencia en contactos
            $contact = $this->findMatchingContact($allContacts, $cleanPhone, $email, $ci);

            // Determinar categoría destino
            $categoryName = $this->resolveCategoryName($row, $rule, $allCategories);

            if ($contact) {
                $matchedCount++;
                $currentStatusName = $contact->customStatus?->name ?? $contact->status ?? 'Sin estado';
                $previewRows[] = [
                    'row_index' => $index + 1,
                    'input_name' => $name ?: $contact->name,
                    'input_phone' => $rawPhone ?: $contact->phone,
                    'input_ci' => $ci,
                    'input_email' => $email ?: $contact->email,
                    'status' => 'matched',
                    'action' => "Actualizar estado: \"{$currentStatusName}\" ➔ \"{$targetStatus?->name}\"",
                    'matched_contact' => [
                        'id' => $contact->id,
                        'name' => $contact->name,
                        'phone' => $contact->phone,
                        'current_status' => $currentStatusName,
                    ],
                    'target_status' => $targetStatus?->name ?? 'N/A',
                    'target_category' => $categoryName,
                ];
            } else {
                if ($autoCreate) {
                    $toCreateCount++;
                    $previewRows[] = [
                        'row_index' => $index + 1,
                        'input_name' => $name ?: 'Nuevo Contacto',
                        'input_phone' => $rawPhone,
                        'input_ci' => $ci,
                        'input_email' => $email,
                        'status' => 'to_create',
                        'action' => "Crear nuevo contacto matriculado",
                        'matched_contact' => null,
                        'target_status' => $targetStatus?->name ?? 'N/A',
                        'target_category' => $categoryName,
                    ];
                } else {
                    $unmatchedCount++;
                    $previewRows[] = [
                        'row_index' => $index + 1,
                        'input_name' => $name,
                        'input_phone' => $rawPhone,
                        'input_ci' => $ci,
                        'input_email' => $email,
                        'status' => 'unmatched',
                        'action' => 'No encontrado (sin acción)',
                        'matched_contact' => null,
                        'target_status' => $targetStatus?->name ?? 'N/A',
                        'target_category' => $categoryName,
                    ];
                }
            }
        }

        return response()->json([
            'summary' => [
                'total_rows' => count($validated['rows']),
                'matched' => $matchedCount,
                'to_create' => $toCreateCount,
                'unmatched' => $unmatchedCount,
                'invalid' => $invalidCount,
            ],
            'target_status' => [
                'id' => $targetStatus?->id,
                'name' => $targetStatus?->name,
                'color' => $targetStatus?->color,
            ],
            'preview' => $previewRows,
        ]);
    }

    /**
     * Ejecutar el cotejo atómicamente y actualizar estados, categorías y campos personalizados.
     */
    public function execute(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $userId = $request->user()->id;

        $validated = $request->validate([
            'rows' => ['required', 'array', 'min:1'],
            'rule' => ['required', 'array'],
            'rule.phone_column' => ['required', 'string'],
            'rule.name_column' => ['nullable', 'string'],
            'rule.ci_column' => ['nullable', 'string'],
            'rule.email_column' => ['nullable', 'string'],
            'rule.target_status_id' => ['required', 'string'],
            'rule.category_mode' => ['nullable', 'string', 'in:none,fixed,dynamic'],
            'rule.category_column' => ['nullable', 'string'],
            'rule.fixed_category_id' => ['nullable', 'string'],
            'rule.auto_create_unmatched' => ['nullable', 'boolean'],
            'rule.auto_tag' => ['nullable', 'string'],
            'save_rule' => ['nullable', 'boolean'],
            'rule_name' => ['nullable', 'string', 'max:100'],
        ]);

        $rule = $validated['rule'];
        $autoCreate = (bool) ($rule['auto_create_unmatched'] ?? false);
        $targetStatusId = $rule['target_status_id'];
        $autoTag = trim((string) ($rule['auto_tag'] ?? ''));

        // Cargar contexto
        $targetStatus = CustomStatus::where('organization_id', $organization->id)->findOrFail($targetStatusId);
        $allCategories = Category::where('organization_id', $organization->id)->get();
        $allContacts = Contact::where('organization_id', $organization->id)->get();

        // Preparar Tag si aplica
        $tag = null;
        if (!empty($autoTag)) {
            $tag = Tag::firstOrCreate(
                ['organization_id' => $organization->id, 'name' => $autoTag],
                ['slug' => Str::slug($autoTag) . '-' . Str::random(3), 'color_hex' => '#10b981']
            );
        }

        $updatedContacts = 0;
        $createdContacts = 0;
        $updatedConversations = 0;
        $processedRows = [];

        DB::transaction(function () use (
            $validated,
            $rule,
            $autoCreate,
            $organization,
            $userId,
            $targetStatus,
            $targetStatusId,
            $allCategories,
            $allContacts,
            $tag,
            &$updatedContacts,
            &$createdContacts,
            &$updatedConversations,
            &$processedRows
        ) {
            foreach ($validated['rows'] as $index => $row) {
                $rawPhone = (string) ($row[$rule['phone_column']] ?? '');
                $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
                $name = trim((string) ($row[$rule['name_column'] ?? ''] ?? ''));
                $ci = trim((string) ($row[$rule['ci_column'] ?? ''] ?? ''));
                $email = trim((string) ($row[$rule['email_column'] ?? ''] ?? ''));

                if (empty($cleanPhone) && empty($email) && empty($ci)) {
                    $processedRows[] = array_merge($row, [
                        '_resultado_cotejo' => 'OMITIDO: Sin datos de contacto',
                    ]);
                    continue;
                }

                // Buscar categoría correspondiente
                $category = $this->resolveCategory($row, $rule, $allCategories);

                // Buscar contacto existente
                $contact = $this->findMatchingContact($allContacts, $cleanPhone, $email, $ci);

                if ($contact) {
                    // 1. ACTUALIZAR CONTACTO EXISTENTE
                    $customFields = (array) ($contact->custom_fields ?? []);
                    if (!empty($ci)) $customFields['numero_identidad'] = $ci;
                    foreach ($row as $k => $v) {
                        if (!in_array($k, [$rule['phone_column'], $rule['name_column'] ?? '']) && filled($v)) {
                            $customFields[$k] = $v;
                        }
                    }

                    $contact->update([
                        'custom_status_id' => $targetStatusId,
                        'custom_fields' => $customFields,
                    ]);

                    // Vincular categoría si aplica
                    if ($category) {
                        $contact->categories()->syncWithoutDetaching([
                            $category->id => [
                                'id' => (string) Str::ulid(),
                                'organization_id' => $organization->id,
                                'assigned_by_user_id' => $userId,
                            ]
                        ]);
                    }

                    // Vincular etiqueta
                    if ($tag) {
                        $contact->tags()->syncWithoutDetaching([$tag->id]);
                    }

                    // Actualizar conversaciones del contacto
                    $convs = Conversation::where('contact_id', $contact->id)
                        ->where('organization_id', $organization->id)
                        ->get();

                    foreach ($convs as $conv) {
                        $conv->update(['custom_status_id' => $targetStatusId]);
                        if ($category) {
                            $conv->categories()->syncWithoutDetaching([
                                $category->id => [
                                    'id' => (string) Str::ulid(),
                                    'organization_id' => $organization->id,
                                    'assigned_by_user_id' => $userId,
                                ]
                            ]);
                        }
                        $updatedConversations++;
                    }

                    $updatedContacts++;
                    $processedRows[] = array_merge($row, [
                        '_resultado_cotejo' => "ACTUALIZADO: Estado a {$targetStatus->name}" . ($category ? " ({$category->name})" : ''),
                    ]);
                } elseif ($autoCreate) {
                    // 2. CREAR NUEVO CONTACTO
                    $nameParts = explode(' ', $name ?: 'Nuevo Contacto', 2);
                    $firstName = $nameParts[0];
                    $lastName = $nameParts[1] ?? null;

                    $customFields = [];
                    if (!empty($ci)) $customFields['numero_identidad'] = $ci;
                    foreach ($row as $k => $v) {
                        if (filled($v)) $customFields[$k] = $v;
                    }

                    $newContact = Contact::create([
                        'organization_id' => $organization->id,
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => filled($cleanPhone) ? $cleanPhone : null,
                        'email' => filled($email) ? $email : null,
                        'status' => 'active',
                        'custom_status_id' => $targetStatusId,
                        'custom_fields' => $customFields,
                    ]);

                    if ($category) {
                        $newContact->categories()->syncWithoutDetaching([
                            $category->id => [
                                'id' => (string) Str::ulid(),
                                'organization_id' => $organization->id,
                                'assigned_by_user_id' => $userId,
                            ]
                        ]);
                    }

                    if ($tag) {
                        $newContact->tags()->syncWithoutDetaching([$tag->id]);
                    }

                    $createdContacts++;
                    $processedRows[] = array_merge($row, [
                        '_resultado_cotejo' => "CREADO COMO NUEVO: Estado {$targetStatus->name}" . ($category ? " ({$category->name})" : ''),
                    ]);
                } else {
                    $processedRows[] = array_merge($row, [
                        '_resultado_cotejo' => 'NO ENCONTRADO EN CRM (Sin acción)',
                    ]);
                }
            }

            // Guardar regla como plantilla si el usuario lo solicitó
            if (!empty($validated['save_rule']) && !empty($validated['rule_name'])) {
                $this->persistRule($organization->id, $validated['rule_name'], $rule);
            }
        });

        return response()->json([
            'message' => "Cotejo finalizado con éxito: {$updatedContacts} contactos actualizados a {$targetStatus->name}, {$createdContacts} nuevos creados.",
            'updated_contacts' => $updatedContacts,
            'created_contacts' => $createdContacts,
            'updated_conversations' => $updatedConversations,
            'processed_rows' => $processedRows,
        ]);
    }

    /**
     * Listar reglas de cotejo guardadas de la organización.
     */
    public function getRules(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $setting = WorkspaceSetting::where('organization_id', $organization->id)->first();
        $rules = data_get($setting?->custom_options, 'reconciliation_rules', []);

        return response()->json(['data' => $rules]);
    }

    /**
     * Guardar o actualizar una regla de cotejo reutilizable.
     */
    public function saveRule(Request $request): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'rule' => ['required', 'array'],
        ]);

        $savedRule = $this->persistRule($organization->id, $validated['name'], $validated['rule']);

        return response()->json([
            'message' => 'Regla de cotejo guardada exitosamente.',
            'data' => $savedRule,
        ]);
    }

    /**
     * Eliminar una regla de cotejo por ID.
     */
    public function deleteRule(Request $request, string $id): JsonResponse
    {
        $organization = $request->user()->currentOrganization;
        $setting = WorkspaceSetting::firstOrCreate(['organization_id' => $organization->id]);

        $customOptions = (array) ($setting->custom_options ?? []);
        $rules = (array) ($customOptions['reconciliation_rules'] ?? []);

        $filtered = array_values(array_filter($rules, fn ($r) => ($r['id'] ?? '') !== $id));
        $customOptions['reconciliation_rules'] = $filtered;
        $setting->update(['custom_options' => $customOptions]);

        return response()->json(['message' => 'Regla eliminada exitosamente.']);
    }

    // -------------------------------------------------------------
    // HELPERS INTERNOS DE BÚSQUEDA Y NORMALIZACIÓN
    // -------------------------------------------------------------

    protected function findMatchingContact($allContacts, string $cleanPhone, string $email, string $ci): ?Contact
    {
        // 1. Por Teléfono
        if (!empty($cleanPhone)) {
            $last8 = strlen($cleanPhone) >= 8 ? substr($cleanPhone, -8) : $cleanPhone;
            $contact = $allContacts->first(function ($c) use ($cleanPhone, $last8) {
                $cPhone = preg_replace('/[^0-9]/', '', $c->phone ?? '');
                if (!$cPhone) return false;
                return $cPhone === $cleanPhone || str_ends_with($cPhone, $last8);
            });
            if ($contact) return $contact;
        }

        // 2. Por Email
        if (!empty($email)) {
            $contact = $allContacts->firstWhere('email', $email);
            if ($contact) return $contact;
        }

        // 3. Por C.I. en custom_fields
        if (!empty($ci)) {
            $contact = $allContacts->first(function ($c) use ($ci) {
                $cCi = data_get($c->custom_fields, 'numero_identidad')
                    ?: data_get($c->custom_fields, 'ci')
                    ?: data_get($c->custom_fields, 'documento');
                return $cCi && trim((string) $cCi) === $ci;
            });
            if ($contact) return $contact;
        }

        return null;
    }

    protected function resolveCategory(array $row, array $rule, $allCategories): ?Category
    {
        $mode = $rule['category_mode'] ?? 'none';

        if ($mode === 'fixed' && !empty($rule['fixed_category_id'])) {
            return $allCategories->firstWhere('id', $rule['fixed_category_id']);
        }

        if ($mode === 'dynamic' && !empty($rule['category_column'])) {
            $rawCategoryName = trim((string) ($row[$rule['category_column']] ?? ''));
            if (empty($rawCategoryName)) return null;

            // Normalización para matching flexible
            $cleanNeedle = $this->normalizeString($rawCategoryName);

            return $allCategories->first(function ($cat) use ($cleanNeedle) {
                $cleanCat = $this->normalizeString($cat->name);
                return str_contains($cleanNeedle, $cleanCat) || str_contains($cleanCat, $cleanNeedle);
            });
        }

        return null;
    }

    protected function resolveCategoryName(array $row, array $rule, $allCategories): ?string
    {
        $cat = $this->resolveCategory($row, $rule, $allCategories);
        return $cat?->name;
    }

    protected function normalizeString(string $str): string
    {
        $str = mb_strtolower($str, 'UTF-8');
        $unwanted = [
            'á'=>'a', 'é'=>'e', 'í'=>'i', 'ó'=>'o', 'ú'=>'u',
            'licenciatura en ' => '', 'carrera de ' => '', 'carrera ' => '',
        ];
        return trim(strtr($str, $unwanted));
    }

    protected function persistRule(string $organizationId, string $name, array $ruleData): array
    {
        $setting = WorkspaceSetting::firstOrCreate(['organization_id' => $organizationId]);

        $customOptions = (array) ($setting->custom_options ?? []);
        $rules = (array) ($customOptions['reconciliation_rules'] ?? []);

        $newRule = [
            'id' => (string) Str::ulid(),
            'name' => $name,
            'phone_column' => $ruleData['phone_column'] ?? 'phone_number',
            'name_column' => $ruleData['name_column'] ?? 'nombre_completo',
            'ci_column' => $ruleData['ci_column'] ?? 'numero_identidad',
            'email_column' => $ruleData['email_column'] ?? 'email_address',
            'target_status_id' => $ruleData['target_status_id'] ?? null,
            'category_mode' => $ruleData['category_mode'] ?? 'dynamic',
            'category_column' => $ruleData['category_column'] ?? 'nombre_carrera',
            'fixed_category_id' => $ruleData['fixed_category_id'] ?? null,
            'auto_create_unmatched' => (bool) ($ruleData['auto_create_unmatched'] ?? true),
            'auto_tag' => $ruleData['auto_tag'] ?? 'Inscrito',
            'updated_at' => now()->toIso8601String(),
        ];

        // Reemplazar si ya existe una regla con el mismo nombre
        $existingIndex = null;
        foreach ($rules as $idx => $r) {
            if (($r['name'] ?? '') === $name) {
                $existingIndex = $idx;
                break;
            }
        }

        if ($existingIndex !== null) {
            $newRule['id'] = $rules[$existingIndex]['id'];
            $rules[$existingIndex] = $newRule;
        } else {
            $rules[] = $newRule;
        }

        $customOptions['reconciliation_rules'] = $rules;
        $setting->update(['custom_options' => $customOptions]);

        return $newRule;
    }
}
