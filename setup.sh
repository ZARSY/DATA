#!/bin/bash

set -e

# Setup .env jika belum ada
if [ ! -f .env ]; then
  cat <<EOF > .env
UID=$(id -u)
GID=$(id -g)
APP_PORT=8000
DB_PORT=33060
DB_DATABASE=laravel
DB_USERNAME=sail
DB_PASSWORD=password
DB_ROOT_PASSWORD=secret
REDIS_PORT=6379
EOF
  echo "✅ Environment file (.env) created."
fi

# Build container
docker compose build

# Buat Laravel jika belum ada
if [ ! -f app/artisan ]; then
  mkdir -p app
  docker compose run --rm app bash -c "cd /var/www/html && composer create-project laravel/laravel:^11.0 ."
fi

# Install Filament jika belum
if [ -f app/artisan ] && [ ! -d app/vendor/filament ]; then
  docker compose run --rm app composer require filament/filament
fi

# Jalankan npm install dan build Vite
docker compose run --rm node sh -c "npm install && npm run build"

# Jalankan semua service
docker compose up -d

echo "✅ Laravel + Vite + Filament is ready at http://localhost:809"
