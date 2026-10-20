# syntax=docker/dockerfile:1

FROM php:8.3-apache-bookworm

# System libraries and PHP extensions required by composer.lock
# (pdo_pgsql for PostgreSQL, sockets for php-amqplib, zip for Composer).
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libzip-dev unzip \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql sockets zip bcmath opcache \
    && apt-get purge -y --auto-remove \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Listen on an unprivileged port so the container can run as www-data.
RUN a2enmod rewrite headers \
    && sed -i 's/^Listen 80$/Listen 8080/' /etc/apache2/ports.conf

WORKDIR /var/www/html

# Install dependencies first to keep this layer cached between code changes.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction --no-progress

COPY . .
RUN composer dump-autoload --optimize --no-dev --no-interaction \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY --chmod=0755 docker/entrypoint.sh /usr/local/bin/entrypoint.sh

USER www-data

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD curl -fsS http://127.0.0.1:8080/up || exit 1

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
