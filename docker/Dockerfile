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

# NOSONAR docker:S6471 — entrypoint runs as root briefly to chown fresh named volumes, then setpriv drops to www-data for the long-running process tree (see docker/entrypoint.sh and docker/supervisord.conf)
FROM dunglas/frankenphp:1-php8.5-bookworm

WORKDIR /app

# Slim layer: runtime deps only. util-linux ships `setpriv`, used by the
# entrypoint to drop privileges after the root-phase chown of runtime
# mounted volumes — pin it explicitly so the entrypoint doesn't depend
# on transitive deps of supervisor.
RUN apt-get update \
    && apt-get install -y --no-install-recommends supervisor util-linux \
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
    && chown -R www-data:www-data /app /app/storage /app/public \
    && chmod -R 775 /app/storage

# Runtime named volumes (/data, /config for Caddy BoltDB and runtime
# config) are root-owned at first mount — see docker/docker-compose.yml's
# spora-init service which runs as root once to chown them, then exits.
# The long-running children (frankenphp, worker) run as www-data
# via supervisord's `user=` directive in docker/supervisord.conf.

USER www-data

EXPOSE 80 443 443/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD []
