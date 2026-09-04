#!/bin/sh
set -eu

APP_KEY_FILE="/var/www/html/storage/docker/app_key"

if [ -z "${APP_KEY:-}" ]; then
    if [ ! -s "$APP_KEY_FILE" ]; then
        mkdir -p "$(dirname "$APP_KEY_FILE")"
        php -r 'echo "base64:" . base64_encode(random_bytes(32));' > "$APP_KEY_FILE"
        chmod 600 "$APP_KEY_FILE"
    fi
    APP_KEY="$(cat "$APP_KEY_FILE")"
    export APP_KEY
fi

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
chmod -R ug+rwX storage bootstrap/cache

echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT}..."
attempt=0
until php -r '
try {
    new PDO(
        "mysql:host=" . getenv("DB_HOST") . ";port=" . getenv("DB_PORT") . ";dbname=" . getenv("DB_DATABASE"),
        getenv("DB_USERNAME"),
        getenv("DB_PASSWORD")
    );
} catch (Throwable $exception) {
    exit(1);
}
'; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 30 ]; then
        echo "MySQL did not become available in time." >&2
        exit 1
    fi
    sleep 2
done

php artisan config:clear --no-ansi
php artisan migrate --force --no-interaction --no-ansi

exec "$@"
