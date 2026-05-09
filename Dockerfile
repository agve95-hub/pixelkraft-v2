# ── Stage 1: PHP dependencies ──────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS php-deps

# System packages required by PHP extensions and the app
RUN apk add --no-cache \
        git \
        curl \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
        zip \
        unzip \
        shadow

# PHP extensions
RUN docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache

# Redis extension via PECL
RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --no-autoloader \
        --no-interaction \
        --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --no-dev


# ── Stage 2: Node.js assets ────────────────────────────────────────────────
FROM node:20-alpine AS node-deps

WORKDIR /var/www/html

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
RUN npm run build


# ── Stage 3: Production image ──────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS production

# Same system packages + extensions as stage 1 (no dev tools)
RUN apk add --no-cache \
        git \
        curl \
        libpng \
        libjpeg-turbo \
        libwebp \
        freetype \
        icu \
        oniguruma \
        libzip \
        nginx \
        supervisor \
        shadow

RUN apk add --no-cache \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apk del \
        libpng-dev \
        libjpeg-turbo-dev \
        libwebp-dev \
        freetype-dev \
        icu-dev \
        oniguruma-dev \
        libzip-dev

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# PHP config
COPY docker/php/php.ini /usr/local/etc/php/conf.d/platform.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini

WORKDIR /var/www/html

# Copy app (compiled vendor from stage 1, compiled assets from stage 2)
COPY --from=php-deps  /var/www/html/vendor  ./vendor
COPY --from=node-deps /var/www/html/public  ./public
COPY . .

RUN mkdir -p storage/logs storage/framework/{cache,sessions,views} bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
