# syntax=docker/dockerfile:1

# ── Stage 1: PHP dependencies via Composer ───────────────────────────────────
# The frontend is NOT built here — it ships as a prebuilt Composer package
# (spora-ai/spora-frontend, type: spora-frontend). spora-ai/installer ^1.4
# routes it into public/spora/ during `composer install`. No Node toolchain
# is needed in this image.

FROM composer:2.8 AS deps-builder

WORKDIR /app

# Install the extensions composer.json declares (pdo_mysql, etc.) so the
# platform check in `composer install` doesn't fail. The runtime stage
# also installs pdo_mysql; this stage only needs them present to satisfy
# the platform check while resolving deps.
RUN docker-php-ext-install pdo_mysql

# Install PHP deps (composer image has ext-zip baked in)
COPY composer.json composer.lock* ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

# ── Stage 2: Production runtime ───────────────────────────────────────────────
FROM dunglas/frankenphp:1-php8.5-bookworm

WORKDIR /app

# Runtime deps only (no build tools). Install supervisor + the runtime PHP
# extensions in one layer to keep the image slim (S7031).
RUN apt-get update \
    && apt-get install -y --no-install-recommends supervisor \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# Copy application sources. Explicit per-directory instead of `COPY . .` so
# S6470 cannot pull in anything outside this whitelist — .dockerignore already
# filters most junk, but explicit > implicit for security findings.
#
# `storage/` is intentionally NOT copied: it is created and chowned at runtime
# by the RUN layer below, and copying it would risk shipping the SQLite DB and
# `secret.key` (encryption key for tool settings) into the image.
COPY app ./app
COPY bin ./bin
COPY public ./public

# Copy PHP deps from deps-builder (vendor/ includes the frontend dist
# already routed by spora-ai/installer — no separate frontend stage)
COPY --from=deps-builder /app/vendor /app/vendor

# Copy supervisord config (located in docker/ but needed at app root)
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