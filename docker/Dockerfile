# syntax=docker/dockerfile:1

# Composer itself, taken from the official Composer image. The version is
# pinned, and no code is fetched and run at build time.
FROM composer:2.11.2 AS composer-bin

# Composer stage — no Node toolchain; spora-ai/installer routes the
# prebuilt frontend into public/spora/.
#
# The base is the official PHP image rather than the Composer image, because
# the Composer image is pinned by Composer version and its PHP floats — and
# it floats per architecture, so the same tag can carry a PHP the runtime
# stage does not satisfy. That made `composer install` abort on a dependency
# capping its PHP range below what the base image happened to ship. Pinning
# the base to the runtime's exact PHP patch means the two stages cannot
# disagree, and no platform requirement has to be ignored.
FROM php:8.5.12-cli-alpine AS deps-builder

WORKDIR /app

# composer.json requires ext-pdo_mysql, and spora-ai/spora-core requires
# ext-zip; install them here so the platform check during `composer install`
# doesn't abort. The Composer image used to bundle ext-zip, the official PHP
# image does not.
#
# git is what cweagans/composer-patches applies a plugin's patches with. The
# Composer image carried it, the official PHP image does not, and a plugin
# that ships a patch would fail the install without it.
RUN apk add --no-cache libzip-dev git \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apk/*

COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer

# The Composer image set these; copying its binary does not carry them.
ENV COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_HOME=/tmp/composer

COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p plugins public/plugins

# Runtime
# FrankenPHP and PHP are pinned to exact releases. The floating
# `1-php8.5` tag moved to a new Caddy and Mercure hub underneath a
# running deployment once already, which broke every boot, and it hides
# which PHP patch is actually running.
#
# The pin names the PHP patch because the PHP is what a host's extensions
# are built against; it does not mean the tag is current. The pinned release
# is a security one: its most severe advisory let `php_server` run an
# uploaded file as PHP.
FROM dunglas/frankenphp:1.13.5-php8.5.12-bookworm

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
# Runtime-layer init: drop entrypoint, create storage dir, chown app, pin PHP error_log to stderr so docker logs captures PHP fatals alongside framework logs.
RUN chmod +x /entrypoint.sh \
    && mkdir -p /app/storage/logs \
    && chown -R www-data:www-data /app /app/storage /app/public \
    && echo "error_log = php://stderr" > /usr/local/etc/php/conf.d/zz-spora-error-log.ini

# Sonar S6471 acknowledged Safe: entrypoint chowns the named volumes
# (/app/storage, /data, /config) then `exec gosu www-data supervisord`.
# Same pattern as Postgres / MariaDB / Redis Official Images and
# LinuxServer.io — root phase is brief and non-interactive.
USER root

EXPOSE 80 443 443/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD []
