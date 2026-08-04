#!/bin/sh
set -e

# Named volumes (spora_storage, caddy_data, caddy_config) come up
# root-owned on first mount. Tolerate bind-mounts that don't need this
# by swallowing the chown error — root can only chown what root owns.
echo "Fixing ownership of named volumes (no-op after first boot)..."
chown -R www-data:www-data /app/storage /data /config 2>/dev/null || true

echo "Running Spora setup..."
php /app/bin/spora spora:setup

# Drop privileges for the long-running process tree (frankenphp + worker).
# gosu is set here rather than in supervisord.conf so the supervisor
# process itself is unprivileged.
echo "Setup complete. Starting services as www-data..."
exec gosu www-data /usr/bin/supervisord -c /app/supervisord.conf
