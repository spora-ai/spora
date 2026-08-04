# Slim layer: runtime deps only. `gosu` is the privilege-drop helper used
# by docker/entrypoint.sh after the one-shot runtime chown of the named
# volumes — chosen over setpriv for the same reason Postgres / MariaDB /
# Redis Official Images use it: it exec's the target without a shell layer.
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

# S6470 acknowledged Safe: entrypoint runs as root briefly to chown the
# /app/storage, /data (Caddy), and /config (Caddy) named volumes on
# first mount — Docker always creates them root-owned. supervisord (and
# therefore frankenphp + the worker) is then exec'd as www-data via
# `gosu`. Same convention as Postgres, Redis, MariaDB Official Images,
# and every LinuxServer.io image.
USER root

EXPOSE 80 443 443/udp

ENTRYPOINT ["/entrypoint.sh"]
CMD []
