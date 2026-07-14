# syntax=docker/dockerfile:1
FROM php:8.2-fpm AS base

# System dependencies
RUN apt-get update && apt-get install -y \
    git curl libpng-dev libonig-dev libxml2-dev \
    libzip-dev zip unzip zlib1g-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Core PHP extensions
RUN docker-php-ext-install \
    pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# OpenTelemetry PHP extension — provides the auto-instrumentation hook
# required by open-telemetry/opentelemetry-auto-laravel
RUN pecl install opentelemetry \
    && docker-php-ext-enable opentelemetry

# gRPC extension — used by open-telemetry/transport-grpc to export spans
RUN pecl install grpc \
    && docker-php-ext-enable grpc

# Protobuf extension — improves serialization performance (optional but recommended)
RUN pecl install protobuf \
    && docker-php-ext-enable protobuf

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install PHP dependencies first (better layer caching)
COPY composer.json composer.lock ./
RUN composer install \
    --optimize-autoloader \
    --no-dev \
    --no-interaction \
    --no-scripts

# Copy application code
COPY . .

# Run post-install scripts (package:discover etc.)
RUN composer run-script post-autoload-dump

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000
CMD ["php-fpm"]
