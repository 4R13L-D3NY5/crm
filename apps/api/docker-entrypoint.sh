#!/bin/sh
set -e

# Wait for database to become available
if [ -n "$DB_HOST" ]; then
    echo "[Entrypoint] Verifying database connectivity on $DB_HOST:${DB_PORT:-5432}..."
    while ! nc -z "$DB_HOST" "${DB_PORT:-5432}"; do
        echo "[Entrypoint] Database not ready yet, retrying in 2 seconds..."
        sleep 2
    done
    echo "[Entrypoint] Database is reachable."
fi

# Link public storage if not already linked
php artisan storage:link --no-interaction 2>/dev/null || true

# Run database migrations
if [ "$RUN_MIGRATIONS" != "false" ]; then
    echo "[Entrypoint] Running database migrations..."
    php artisan migrate --force --no-interaction
fi

# Run initial seed if requested
if [ "$SEED_DEMO" = "true" ]; then
    echo "[Entrypoint] Checking demo seed data..."
    php artisan db:seed --class=DemoCrmSeeder --force --no-interaction || true
fi

# Production cache optimizations
if [ "$APP_ENV" = "production" ]; then
    echo "[Entrypoint] Optimizing caches for production..."
    php artisan config:cache || true
    php artisan route:cache || true
    php artisan view:cache || true
fi

echo "[Entrypoint] Starting application process: $@"
exec "$@"
