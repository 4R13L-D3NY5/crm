# Backend Guidelines

## Objetivo

Mantener un backend Laravel modular, consistente y predecible para un CRM multiempresa.

## Stack backend

- Laravel
- MySQL 8+
- Redis
- Sanctum
- Horizon
- Reverb o Soketi
- Spatie Laravel Permission

## Reglas obligatorias

1. Los controladores no contienen logica de negocio.
2. Toda escritura pasa por una `Action`.
3. Toda validacion vive en `FormRequest`.
4. Toda respuesta HTTP sale por `Resource`.
5. Toda autorizacion pasa por `Policy`.
6. Todo proceso lento o externo va a `Job`.
7. Todo webhook debe ser idempotente.
8. Todo modulo debe incluir tests.
9. Toda entidad principal usa ULID.
10. Toda tabla multiempresa usa `organization_id`.

## Estructura por modulo

```text
Modules/Example/
├── Actions/
├── Data/
├── Enums/
├── Events/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   └── Resources/
├── Jobs/
├── Models/
├── Policies/
├── Queries/
├── Services/
├── routes.php
└── Tests/
```

## Convenciones de nombres

- Modelos en singular: `Contact`, `Company`, `Conversation`
- Tablas en plural snake_case: `contacts`, `companies`, `conversations`
- Acciones con verbo + entidad: `CreateContactAction`
- Requests con verbo + entidad: `CreateContactRequest`
- Resources por entidad: `ContactResource`
- Policies por entidad: `ContactPolicy`
- Jobs orientados a procesos concretos: `ProcessWhatsAppWebhookJob`

## Base de datos y MySQL

### Reglas de modelado

- MySQL 8+ es el motor objetivo.
- Todas las tablas funcionales multiempresa deben indexar `organization_id`.
- Usar `char(26)` o equivalente para ULID segun la convencion adoptada en migraciones.
- Definir `foreign key` explicitas cuando aplique.
- Indexar filtros frecuentes: estado, responsable, fecha, telefono, email, externos.
- Evitar nombres reservados de MySQL para columnas o tablas.

### Reglas practicas

- Preferir `utf8mb4`.
- Usar `InnoDB`.
- Definir collation consistente a nivel proyecto.
- Considerar longitud de indices al modelar valores unicos largos.
- Para JSON, usar columna `json` solo cuando el dato sea semiestructurado y no funcional para busquedas criticas.

## API y capa HTTP

- Rutas API versionadas bajo `/api`.
- Health check disponible desde el bootstrap inicial.
- Listados con paginacion server-side.
- Errores de validacion y negocio con formato consistente.

## Autorizacion y tenancy

- El contexto de organizacion debe resolverse temprano en middleware.
- Ninguna consulta funcional debe ignorar la organizacion actual.
- Las policies deben validar pertenencia y permisos.

## Webhooks e integraciones

- Guardar primero el payload crudo.
- Procesar despues por job.
- Manejar idempotencia con claves externas unicas o tablas de tracking.
- Persistir eventos relevantes para auditoria y reintentos.

## Testing

Cada modulo debe incluir al menos:

- tests feature de endpoints principales
- tests de autorizacion
- tests de validacion
- tests de reglas de negocio criticas
- tests de idempotencia si hay webhooks

### Tipos de prueba recomendados

- `Feature`: endpoints, middleware, autenticacion, policies
- `Unit`: servicios puros, value objects, reglas aisladas
- `Integration`: integraciones con colas, eventos o proveedores fake

## Definition of Done por modulo backend

- migraciones listas
- modelos y relaciones definidos
- acciones implementadas
- requests implementados
- resources implementados
- policies implementadas
- rutas registradas
- tests principales pasando
- sin logica de negocio en controladores
