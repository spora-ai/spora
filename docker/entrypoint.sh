#!/bin/sh
set -e

# Phase 1 (root): repair runtime-mounted volumes. Named volumes come up
# root-owned regardless of the build-time chown in the Dockerfile, so the
# Caddy BoltDB at /data/caddy/mercure.db and FrankenPHP's runtime config
# at /config are unwritable by www-data until we re-chown at boot. A bind
# mount from the host is a different case — the host UID/GID owns the
# files and chown returns EPERM. Tolerate that with `|| true`; the
# children only need READ access to /app/storage for the SQLite DB.
chown -R www-data:www-data /app 2>/dev/null || true
chown -R www-data:www-data /data 2>/dev/null || true
chown -R www-data:www-data /config 2>/dev/null || true

echo "Running Spora setup..."
php /app/bin/spora spora:setup

# Setup may have created new files in /app/storage (database.sqlite,
# secret.key, .schema_stamp, …); chown only what we just created.
chown -R www-data:www-data /app/storage 2>/dev/null || true

echo "Setup complete. Dropping privileges and starting services..."

# Phase 2 (www-data): long-running children run with reduced privileges
# via supervisord, which itself spawns frankenphp and the worker as
# www-data via its `user=` directive.
exec setpriv --reuid=$(id -u www-data) --regid=$(id -g www-data) \
     --init-groups -- /usr/bin/supervisord -c /app/supervisord.conf
