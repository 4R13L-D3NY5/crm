# Database Guidelines

## Motor de base de datos

El proyecto usara MySQL 8+ como base de datos principal.

Esta decision reemplaza PostgreSQL del plan inicial y afecta:

- contenedores e infraestructura
- configuracion Laravel
- migraciones y tipos de datos
- indices
- convenciones de collation y charset

## Reglas globales

- Motor: InnoDB
- Charset: `utf8mb4`
- Collation: definir una sola convencion global y mantenerla estable
- Claves primarias principales: ULID
- Datos multiempresa: columna obligatoria `organization_id`

## Entidades base

### Auth + Tenancy

- `organizations`
- `users`
- `organization_user`
- `roles`
- `permissions`
- `invitations`
- `sessions`

### Contactos y empresas

- `contacts`
- `companies`
- `contact_company`
- `contact_emails`
- `contact_phones`
- `contact_addresses`
- `contact_tags`
- `tags`
- `custom_fields`
- `custom_field_values`

### Pipeline comercial

- `pipelines`
- `pipeline_stages`
- `deals`
- `deal_contacts`
- `deal_companies`
- `deal_activities`
- `deal_stage_histories`

### Actividades

- `activities`
- `activity_types`
- `tasks`
- `notes`
- `reminders`

### Inbox conversacional

- `channels`
- `channel_accounts`
- `conversations`
- `conversation_participants`
- `messages`
- `message_attachments`
- `message_statuses`
- `conversation_assignments`
- `conversation_tags`
- `conversation_notes`

### WhatsApp

- `whatsapp_accounts`
- `whatsapp_phone_numbers`
- `whatsapp_templates`
- `whatsapp_webhook_events`
- `whatsapp_message_mappings`

### Automatizaciones

- `automation_rules`
- `automation_triggers`
- `automation_conditions`
- `automation_actions`
- `automation_runs`

### Agente IA

- `ai_agents`
- `ai_agent_tools`
- `ai_agent_runs`
- `ai_agent_messages`
- `knowledge_sources`
- `knowledge_documents`
- `knowledge_chunks`

## Reglas de modelado

### ULID

- Usar ULID en tablas principales para mejorar interoperabilidad y orden aproximado temporal.
- Guardar como `char(26)` o la abstraccion equivalente del framework si el proyecto lo soporta claramente.

### organization_id

- Toda tabla funcional multiempresa debe incluir `organization_id`.
- Debe existir indice por `organization_id`.
- Si la tabla se consulta por estado o fecha, usar indices compuestos cuando tenga sentido.

### Campos JSON

Usar JSON solo para:

- payloads crudos
- configuraciones variables
- metadata de terceros

No usar JSON para datos relacionales que luego requieran filtros criticos del CRM.

### Fechas y auditoria

- Mantener `created_at` y `updated_at` en tablas funcionales.
- Agregar `deleted_at` solo cuando la eliminacion logica tenga sentido funcional.
- Para historiales importantes, usar tablas especificas de auditoria en vez de sobrecargar una sola columna.

## Indices recomendados

### Contactos

- `organization_id`
- `(organization_id, last_name)`
- `(organization_id, status)`
- telefonos normalizados
- emails normalizados

### Empresas

- `organization_id`
- nombre
- identificadores fiscales si aplica

### Deals

- `(organization_id, pipeline_id)`
- `(organization_id, stage_id)`
- `(organization_id, owner_id)`
- `(organization_id, status)`

### Conversations

- `(organization_id, status)`
- `(organization_id, assignee_id)`
- `(organization_id, last_message_at)`

### WhatsApp

- identificadores externos unicos
- claves de idempotencia
- fechas de recepcion/procesamiento

## Reglas para webhooks

- Persistir primero el payload crudo en `whatsapp_webhook_events`.
- Guardar metadatos de procesamiento:
  - proveedor
  - tipo de evento
  - recibido en
  - procesado en
  - estado
  - hash o clave externa si aplica

## Busqueda y escalabilidad

En etapas tempranas:

- usar indices MySQL y filtros bien disenados
- evitar complejidad prematura

Cuando el volumen lo justifique:

- incorporar Scout + Meilisearch para busquedas complejas

## Definition of Done de base de datos por modulo

- migraciones creadas
- llaves foraneas definidas cuando corresponda
- indices principales definidos
- soporte multiempresa resuelto
- campos de auditoria definidos
- estrategia de idempotencia definida si aplica
