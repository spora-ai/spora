# syntax=docker/dockerfile:1

# Composer stage — no Node toolchain; spora-ai/installer routes the
# prebuilt frontend into public/spora/.
FROM composer:2.8 AS deps-builder

WORKDIR /app

# Platform check needs pdo_mysql present.
RUN docker-php-ext-install pdo_mysql

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Runtime
FROM dunglas/frankenphp:1-php8.5-bookworm

WORKDIR /app

# Slim layer: runtime deps only.
RUN apt-get update \
    && apt-get install -y --no-install-recommends supervisor \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Explicit per-directory COPY (S6470): storage/ intentionally omitted
# to avoid shipping local SQLite DB and secret.key.
COPY app ./app
COPY bin ./bin
COPY public ./public
COPY --from=deps-builder /app/vendor /app/vendor
COPY docker/supervisord.conf /app/supervisord.conf
COPY docker/frankenphp.conf /app/frankenphp.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && mkdir -p /app/storage/logs \
    && chown -R www-data:www-data /app /app/storage /app/public /data /config \
    && chmod -R 775 /app/storage

USER www-data

EXPOSE 80 443 443/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD []
