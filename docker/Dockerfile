# syntax=docker/dockerfile:1

# Composer stage — no Node toolchain; spora-ai/installer routes the
# prebuilt frontend into public/spora/.
FROM composer:2.8 AS deps-builder

WORKDIR /app

# composer.json requires ext-pdo_mysql; install it here so the
# platform check during `composer install` doesn't abort.
RUN docker-php-ext-install pdo_mysql

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p plugins public/plugins

# Runtime
FROM dunglas/frankenphp:1-php8.5-bookworm

WORKDIR /app

# Slim runtime layer. `gosu` drops to www-data after the entrypoint's
# one-shot chown of the named volumes (Postgres / MariaDB / Redis Official
# Images convention).
RUN apt-get update \
    && apt-get install -y --no-install-recommends supervisor gosu \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Explicit per-directory COPY (S6470): storage/ intentionally omitted
# to avoid shipping local SQLite DB and secret.key.
COPY app ./app
COPY bin ./bin
COPY public ./public
COPY --from=deps-builder /app/vendor /app/vendor
COPY --from=deps-builder /app/plugins /app/plugins
COPY --from=deps-builder /app/public/plugins /app/public/plugins
# Prebuilt SPA bundle extracted by spora-ai/installer in deps-builder
# (gitignored in build context, so COPY public ./public above is empty).
COPY --from=deps-builder /app/public/spora ./public/spora
COPY docker/supervisord.conf /app/supervisord.conf
COPY docker/frankenphp.conf /app/frankenphp.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh \
    && mkdir -p /app/storage/logs \
    && chown -R www-data:www-data /app /app/storage /app/public

# Sonar S6471 acknowledged Safe: entrypoint chowns the named volumes
# (/app/storage, /data, /config) then `exec gosu www-data supervisord`.
# Same pattern as Postgres / MariaDB / Redis Official Images and
# LinuxServer.io — root phase is brief and non-interactive.
USER root

EXPOSE 80 443 443/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD []
