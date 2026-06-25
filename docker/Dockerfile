# syntax=docker/dockerfile:1

# ── Stage 1: PHP dependencies via Composer ───────────────────────────────────
# The frontend is NOT built here — it ships as a prebuilt Composer package
# (spora-ai/spora-frontend, type: spora-frontend). spora-ai/installer ^1.2
# routes it into public/dist/ during `composer install`. No Node toolchain
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

# Runtime deps only (no build tools)
RUN apt-get update && apt-get install -y --no-install-recommends supervisor && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo_mysql

# Copy application
COPY . .

# Copy PHP deps from deps-builder (vendor/ includes the frontend dist
# already routed by spora-ai/installer — no separate frontend stage)
COPY --from=deps-builder /app/vendor /app/vendor

# Copy supervisord config (located in docker/ but needed at app root)
COPY docker/supervisord.conf /app/supervisord.conf
COPY docker/frankenphp.conf /app/frankenphp.conf

# Copy and make entrypoint executable
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

RUN mkdir -p /app/storage/logs && chmod -R 775 /app/storage

EXPOSE 80 443 443/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD []