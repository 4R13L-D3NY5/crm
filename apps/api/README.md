# API CRM

Backend del CRM conversacional construido con Laravel.

## Stack

- Laravel 13
- PHP 8.3
- MySQL 8
- Redis
- Laravel Sanctum

## Estructura objetivo

```text
app/
|-- Modules/
|-- Shared/
`-- Models/
```

## Comandos utiles

```powershell
php artisan serve
php artisan serve --port=8010
composer serve:crm
php artisan test
php artisan migrate
php artisan queue:listen
```

## Endpoint inicial

- `GET /api/health`

## Notas

- La autenticacion principal de la SPA usara Sanctum con cookies y CSRF.
- Los modulos funcionales se iran agregando dentro de `app/Modules`.
- Configuracion local inicial: MySQL obligatorio, Redis opcional.
- En arranque local temprano se usan `QUEUE_CONNECTION=database` y `CACHE_STORE=database`.
- Puerto local recomendado del backend: `8010`.
- Si cambias el puerto del frontend, actualiza `SANCTUM_STATEFUL_DOMAINS` y `CORS_ALLOWED_ORIGINS` en `.env`.
