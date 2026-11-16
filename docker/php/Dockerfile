# syntax=docker/dockerfile:1.7

# --- Étape 1 : dépendances PHP ---
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts --ignore-platform-reqs

# --- Étape 2 : assets front ---
FROM node:24-alpine AS assets
WORKDIR /app
RUN corepack enable
COPY package.json pnpm-lock.yaml* ./
RUN pnpm install --frozen-lockfile || pnpm install
COPY resources ./resources
COPY vite.config.js ./
RUN pnpm run build

# --- Base commune PHP-FPM ---
FROM php:8.5-fpm-bookworm AS base
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN apt-get update && apt-get install -y --no-install-recommends unzip \
    && install-php-extensions pdo_pgsql intl zip pcntl opcache bcmath redis \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html

# --- Cible développement : le code est monté en volume ---
FROM base AS dev
ARG UID=1000
ARG GID=1000
RUN groupadd -g ${GID} app && useradd -u ${UID} -g ${GID} -m app
USER app

# --- Cible production : code embarqué, utilisateur non root ---
FROM base AS prod
RUN groupadd -g 1000 app && useradd -u 1000 -g 1000 -m app
COPY docker/php/prod.ini /usr/local/etc/php/conf.d/zz-prod.ini
COPY --chown=app:app --from=vendor /app /var/www/html
COPY --chown=app:app --from=assets /app/public/build /var/www/html/public/build
RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R app:app storage bootstrap/cache
USER app
RUN php artisan package:discover --ansi

# --- Cible web : Nginx avec les fichiers publics embarqués (production) ---
FROM nginx:1.29-alpine AS web
COPY --from=vendor /app/public /var/www/html/public
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/nginx/prod.conf.template /etc/nginx/templates/default.conf.template
