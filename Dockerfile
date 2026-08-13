FROM php:8.1-cli

RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev libpng-dev libonig-dev \
    && docker-php-ext-install pdo_mysql zip mbstring

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html