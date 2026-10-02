# Compiles the Blade + Vue 3 frontend (resources/js, resources/css) into
# public/build via Vite. Kept as its own stage so the PHP stage below never
# needs Node installed at runtime — only the compiled output is copied over.
FROM node:20-alpine AS frontend-build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN npm run build

# Single-container production image for DigitalOcean App Platform:
# Nginx + PHP-FPM serve HTTP, Supervisor also runs the queue worker and the
# Laravel scheduler loop in the same container, so one App Platform "Web
# Service" component is enough (no separate Worker component to pay for).
FROM php:8.2-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        nginx \
        supervisor \
        ffmpeg \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libonig-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd bcmath zip exif \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Install dependencies separately so this layer is cached unless composer.*
# actually changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
COPY --from=frontend-build /app/public/build ./public/build
RUN composer dump-autoload --optimize --no-dev

RUN mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Matches docker/php.ini's upload_tmp_dir — guarantees PHP has a writable
# place to buffer multipart uploads regardless of the base image's own /tmp
# permissions.
RUN mkdir -p /tmp/php-uploads && chown www-data:www-data /tmp/php-uploads && chmod 1777 /tmp/php-uploads

COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 8080

ENTRYPOINT ["/entrypoint.sh"]
