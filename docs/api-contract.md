# API Contract

## Objetivo

Definir un contrato uniforme para todos los endpoints del CRM.

## Principios

- Todas las respuestas exitosas devuelven `data`.
- Los listados usan metadatos de paginacion.
- Los errores de validacion devuelven `message` y `errors`.
- Los errores de negocio devuelven `message` y `code`.
- La forma de la respuesta no cambia entre modulos sin decision documentada.

## Listado

```json
{
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 15,
    "total": 100
  },
  "links": {
    "first": "https://example.test/api/contacts?page=1",
    "last": "https://example.test/api/contacts?page=7",
    "prev": null,
    "next": "https://example.test/api/contacts?page=2"
  }
}
```

## Detalle

```json
{
  "data": {
    "id": "01HX1111111111111111111111",
    "name": "Cliente Demo"
  }
}
```

## Creacion o actualizacion exitosa

```json
{
  "data": {
    "id": "01HX1111111111111111111111",
    "name": "Cliente Demo"
  },
  "message": "Registro guardado correctamente."
}
```

## Error de validacion

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "name": [
      "El nombre es obligatorio."
    ]
  }
}
```

## Error de negocio

```json
{
  "message": "No se puede cerrar una conversacion sin responsable.",
  "code": "CONVERSATION_REQUIRES_ASSIGNEE"
}
```

## Error no controlado

```json
{
  "message": "Ha ocurrido un error inesperado."
}
```

## Reglas de paginacion

- Todos los listados usan paginacion server-side.
- `per_page` debe ser configurable dentro de limites razonables.
- Los filtros deben viajar por query string.

## Reglas de filtros

- `search`: texto libre
- `status`: estado funcional
- `assignee_id`: responsable
- `tag`: etiqueta
- `page`: pagina actual
- `per_page`: tamano de pagina

Cada modulo puede agregar filtros propios, pero debe mantener consistencia semantica.

## Reglas de autenticacion

- La SPA principal usa cookies de sesion con Sanctum.
- Las solicitudes mutables deben enviar CSRF.
- No se exponen secretos ni tokens de integracion al frontend.

## Reglas de versionado

- Version inicial bajo `/api`.
- Si aparece un breaking change real, versionar bajo prefijo nuevo, por ejemplo `/api/v2`.

## Reglas para webhooks

- El endpoint puede responder rapido y delegar procesamiento a jobs.
- Los eventos duplicados no deben generar efectos duplicados.
- Los payloads crudos deben persistirse para auditoria.

## Reglas para recursos

- `Resource` define forma de salida.
- No devolver modelos crudos directamente.
- Las relaciones deben exponerse de forma explicita y consistente.
