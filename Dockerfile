FROM php:8.3-fpm-alpine

RUN apk add --no-cache libzip-dev php83-dev build-base autoconf \
    && docker-php-ext-install pdo_mysql zip pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
