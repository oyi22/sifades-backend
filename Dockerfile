FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev libpng-dev libonig-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql zip mbstring gd bcmath pcntl exif

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

RUN cat <<'EOF' > /usr/local/bin/docker-entrypoint.sh
#!/bin/sh
set -e

if [ ! -d "vendor" ]; then
  composer install --no-interaction --prefer-dist
fi

if [ ! -f ".env" ]; then
  cp .env.example .env
fi

if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
  php artisan key:generate
fi

php artisan migrate --force

exec "$@"
EOF

RUN sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh \
    && chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]