# Arquitectura del CRM

## Vision general

El sistema se construira como un monorepo con dos aplicaciones principales:

- `apps/api`: API backend en Laravel
- `apps/web`: SPA administrativa en Quasar + Vue 3 + TypeScript

La decision base es un monolito modular. No se adoptan microservicios en la primera etapa porque el CRM necesita:

- consistencia transaccional
- menor complejidad operativa
- velocidad de desarrollo
- trazabilidad simple entre modulos

Si en el futuro el agente IA o el procesamiento conversacional crecen mucho, podran extraerse como servicios separados.

## Estructura del monorepo

```text
crm-platform/
|-- apps/
|   |-- api/
|   `-- web/
|-- docs/
|-- skills/
`-- README.md
```

## Backend: monolito modular

```text
apps/api/app/
|-- Modules/
|   |-- Auth/
|   |-- Tenancy/
|   |-- Users/
|   |-- Contacts/
|   |-- Companies/
|   |-- Pipelines/
|   |-- Deals/
|   |-- Activities/
|   |-- Conversations/
|   |-- WhatsApp/
|   |-- Automations/
|   |-- AiAgent/
|   |-- Reports/
|   `-- Settings/
`-- Shared/
    |-- Domain/
    |-- Http/
    |-- Support/
    |-- ValueObjects/
    `-- Exceptions/
```

Cada modulo debe seguir la misma estructura:

```text
Modules/Contacts/
|-- Actions/
|-- Data/
|-- Enums/
|-- Events/
|-- Http/
|   |-- Controllers/
|   |-- Requests/
|   `-- Resources/
|-- Jobs/
|-- Models/
|-- Policies/
|-- Queries/
|-- Services/
|-- routes.php
`-- Tests/
```

Regla obligatoria: ningun modulo puede inventar una estructura distinta sin una decision arquitectonica documentada.

## Frontend: modular por dominio

```text
apps/web/src/
|-- app/
|   |-- boot/
|   |-- config/
|   |-- router/
|   `-- plugins/
|-- shared/
|   |-- api/
|   |-- components/
|   |-- composables/
|   |-- constants/
|   |-- layouts/
|   |-- stores/
|   |-- types/
|   `-- utils/
|-- modules/
|   |-- contacts/
|   |-- companies/
|   |-- deals/
|   |-- conversations/
|   |-- whatsapp/
|   |-- automations/
|   |-- ai-agent/
|   |-- reports/
|   `-- settings/
`-- css/
    |-- app.scss
    `-- quasar.variables.scss
```

Cada modulo frontend debe seguir esta estructura:

```text
modules/contacts/
|-- api/
|-- components/
|-- composables/
|-- pages/
|-- routes.ts
|-- schemas/
|-- stores/
|-- types/
`-- utils/
```

## Decisiones tecnicas principales

### Persistencia

- Base de datos principal: MySQL 8+
- Cache, colas y coordinacion: Redis
- Almacenamiento de archivos: local en desarrollo y MinIO opcional

### Autenticacion

- Laravel Sanctum con cookies de sesion y CSRF para la SPA propia
- Tokens personales solo para integraciones futuras o apps externas

### Realtime

- Laravel Reverb o Soketi para eventos en tiempo real

### WhatsApp

- Primer adaptador: Meta WhatsApp Cloud API
- Arquitectura preparada para adaptadores futuros
- Todo webhook se guarda primero como evento crudo

### IA

- Modulo desacoplado con interfaz `AiProvider`
- Inicialmente orientado a sugerencias, no a respuestas automaticas completas

## Multiempresa

La plataforma debe ser multiempresa desde el inicio.

Reglas:

- toda tabla funcional multiempresa incluye `organization_id`
- los endpoints deben resolver el contexto de organizacion actual
- las consultas deben filtrar por organizacion
- las policies deben validar acceso dentro del tenant

## Modulos base del producto

1. Auth + Tenancy
2. Contacts + Companies
3. Pipelines + Deals
4. Activities
5. Conversations Inbox
6. WhatsApp
7. Automations
8. AiAgent
9. Reports
10. Settings

## Definition of Done por etapa

### Fase 0

- documentacion base aprobada
- stack, reglas y estructura definidos

### Fase 1

- backend levanta
- frontend levanta
- MySQL conecta
- Redis conecta
- tests base pasan

### Fase 2 en adelante

Cada modulo debe cumplir:

- backend funcional
- frontend funcional
- autorizacion
- validacion
- tests
- consistencia con contrato API

## Criterios de evolucion

Se permite extraer componentes a servicios separados solo si aparecen sintomas claros:

- tiempos de despliegue o build excesivos
- cuellos de botella aislables
- necesidades de escalado muy diferentes
- dependencia operativa entre equipos

Hasta entonces, la prioridad es mantener una arquitectura simple, consistente y mantenible.
