# Frontend Guidelines

## Objetivo

Construir una SPA administrativa coherente, modular y mantenible sobre Quasar, Vue 3 y TypeScript.

## Stack frontend

- Quasar Framework
- Vue 3
- TypeScript estricto
- Vue Router
- Pinia
- TanStack Vue Query
- Axios o wrapper propio de fetch

## Reglas obligatorias

1. Ningun componente o pagina llama HTTP directamente salvo archivos `api/*.ts`.
2. Las paginas consumen datos mediante composables.
3. Los formularios usan schemas o reglas centralizadas.
4. Las tablas usan paginacion server-side.
5. Los modales reutilizan `AppDialog`.
6. Los estados visuales reutilizan `AppStatusBadge`.
7. Los permisos se validan con `useCan()`.
8. Las rutas de cada modulo viven dentro del modulo.
9. No duplicar tablas, badges, dialogs ni botones recurrentes.
10. No mezclar estilos arbitrarios fuera del sistema de tema.

## Estructura base

```text
src/
├── app/
├── shared/
├── modules/
└── css/
```

## Estructura por modulo

```text
modules/contacts/
├── api/
│   └── contacts.api.ts
├── components/
├── composables/
│   └── useContacts.ts
├── pages/
│   ├── ContactListPage.vue
│   ├── ContactDetailPage.vue
│   └── ContactFormDialog.vue
├── routes.ts
├── schemas/
├── stores/
├── types/
│   └── contact.types.ts
└── utils/
```

## Separacion de responsabilidades

### Pinia

Usar solo para:

- sesion
- usuario actual
- organizacion actual
- preferencias UI
- estado temporal de layout

### TanStack Vue Query

Usar para:

- listas
- detalle
- paginacion
- filtros
- mutaciones
- cache de servidor
- invalidaciones

## Patrones UI obligatorios

### Pagina de listado

- header con titulo, descripcion y accion principal
- filtros visibles
- tabla reutilizable
- acciones por fila
- paginacion server-side
- estados `loading`, `empty`, `error`

### Pagina de detalle

- panel principal
- sidebar secundaria
- timeline
- acciones rapidas

### Formulario modal

- titulo claro
- campos agrupados
- validacion por campo
- boton cancelar
- boton guardar
- estado de envio
- errores API visibles

## Convenciones de nombres

- Paginas: `ContactListPage.vue`
- Dialogos: `ContactFormDialog.vue`
- Composables: `useContacts.ts`
- API clients: `contacts.api.ts`
- Tipos: `contact.types.ts`
- Rutas del modulo: `routes.ts`

## Routing

- Rutas publicas y privadas separadas.
- Guard de autenticacion centralizado.
- Cada modulo exporta sus propias rutas.
- El router raiz compone rutas de modulos.

## Teming y estilos

- Usar Quasar como sistema UI principal.
- Centralizar variables en `quasar.variables.scss`.
- Mantener tokens visuales estables.
- Evitar estilos inline repetidos.
- Construir componentes shared sobre Quasar para homogenizar la UX.

## Testing y calidad

En fases iniciales puede priorizarse la cobertura backend, pero el frontend debe quedar preparado para:

- pruebas de componentes clave
- pruebas de flujos criticos
- validacion de permisos en UI
- manejo consistente de errores

## Definition of Done por modulo frontend

- rutas creadas
- pagina de listado funcional
- pagina de detalle funcional si aplica
- dialogos y formularios conectados
- composables para queries y mutations
- cliente API separado
- tipos base definidos
- uso de componentes shared
- estados visuales consistentes
