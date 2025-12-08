FROM php:8.2-fpm-alpine AS php-base

# System dependencies and PHP extensions
RUN apk add --no-cache \
    bash \
    git \
    nginx \
    supervisor \
    icu-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    libzip-dev \
    oniguruma-dev \
    freetype-dev \
    openssl-dev \
    libxml2-dev \
    zlib-dev \
    curl \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_mysql \
        zip

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Application dependencies
FROM php-base AS vendor
WORKDIR /var/www/html
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader
COPY . .

# Build frontend assets with Vite
FROM node:18-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-progress
COPY . .
RUN npm run build

# Production runtime
FROM php-base AS production
WORKDIR /var/www/html

ENV APP_ENV=production \
    APP_DEBUG=0 \
    PORT=8080 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=128 \
    PHP_OPCACHE_INTERNED_STRINGS_BUFFER=16

# Copy backend (with vendor) and built assets
COPY --from=vendor /var/www/html /var/www/html
COPY --from=frontend /app/public/build ./public/build

# Permissions for Laravel writable dirs and runtime sockets/logs
RUN mkdir -p /run/php /var/log/nginx /var/log/supervisor \
    && chown -R www-data:www-data storage bootstrap/cache public/build /run/php /var/log/nginx /var/log/supervisor

# Nginx, PHP-FPM, Supervisor configuration
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/laravel.ini

EXPOSE 8080
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
