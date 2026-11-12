#!/bin/sh
set -eu

cd /var/www/html

if [ ! -f .env ]; then
    cp .env.example .env
fi

if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

app_key=$(grep '^APP_KEY=' .env | head -n 1 | cut -d= -f2- | tr -d '"' | tr -d "'" | tr -d '[:space:]' || true)
if [ -z "${app_key}" ]; then
    php artisan key:generate --force --ansi
fi

read_env() {
    key=$1
    fallback=$2

    if [ -n "$(printenv "$key" || true)" ]; then
        return 0
    fi

    value=$(grep "^${key}=" .env | head -n 1 | cut -d= -f2- | tr -d '"' || true)
    if [ -z "$value" ]; then
        value=$fallback
    fi

    export "$key=$value"
}

read_env DB_HOST db
read_env DB_PORT 5432
read_env DB_DATABASE fintrack
read_env DB_USERNAME fintrack
read_env DB_PASSWORD fintrack

echo "Waiting for PostgreSQL..."
attempt=0
until php -r 'try { new PDO("pgsql:host=".getenv("DB_HOST").";port=".getenv("DB_PORT").";dbname=".getenv("DB_DATABASE"), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); } catch (Throwable $e) { fwrite(STDERR, $e->getMessage().PHP_EOL); exit(1); }'; do
    attempt=$((attempt + 1))
    if [ "$attempt" -ge 60 ]; then
        echo "PostgreSQL did not become ready." >&2
        exit 1
    fi
    sleep 1
done

php artisan migrate --force --ansi

exec php artisan serve --host=0.0.0.0 --port=8000
