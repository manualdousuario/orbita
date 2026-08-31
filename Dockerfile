# syntax=docker/dockerfile:1

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM serversideup/php:8.4-fpm-nginx
USER root

RUN install-php-extensions ffi imagick intl bcmath exif gd redis \
    && apt-get update \
    && apt-get install -y --no-install-recommends mariadb-client libvips42 \
    && rm -rf /var/lib/apt/lists/* \
    && ldconfig \
    && printf 'ffi.enable=true\n' > /usr/local/etc/php/conf.d/zz-orbita-ffi.ini \
    && printf 'zend.exception_ignore_args=1\n' > /usr/local/etc/php/conf.d/zz-orbita-security.ini


RUN php -r 'exit(extension_loaded("FFI") && ini_get("ffi.enable") ? 0 : 1);' \
    && php -r 'exit(ini_get("zend.exception_ignore_args") ? 0 : 1);' \
    && php -r 'exit(extension_loaded("redis") ? 0 : 1);' \
    && ldconfig -p | grep -q libvips

WORKDIR /var/www/html

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts --no-autoloader

COPY --chown=www-data:www-data . /var/www/html
COPY --from=assets --chown=www-data:www-data /app/public/build /var/www/html/public/build

RUN composer dump-autoload --optimize --no-dev \
    && php artisan filament:assets \
    && chown -R www-data:www-data /var/www/html/vendor /var/www/html/bootstrap/cache

COPY --chmod=755 docker/s6-rc.d/ /etc/s6-overlay/s6-rc.d/

ENV S6_KILL_GRACETIME=30000 \
    S6_SERVICES_GRACETIME=30000 \
    S6_KILL_FINISH_MAXTIME=10000

ENV AUTORUN_ENABLED=true \
    AUTORUN_LARAVEL_MIGRATION_ISOLATION=true


ENV PHP_OPCACHE_ENABLE=1 \
    PHP_OPCACHE_MEMORY_CONSUMPTION=256 \
    PHP_OPCACHE_MAX_ACCELERATED_FILES=20000 \
    PHP_OPCACHE_VALIDATE_TIMESTAMPS=0

ENV PHP_FPM_PM_MAX_CHILDREN=32 \
    PHP_FPM_PM_START_SERVERS=4 \
    PHP_FPM_PM_MIN_SPARE_SERVERS=2 \
    PHP_FPM_PM_MAX_SPARE_SERVERS=8 \
    PHP_FPM_PM_MAX_REQUESTS=1000

USER www-data
