#!/bin/sh
set -e

# Named volumes come up root-owned; bind-mounts may not need this.
echo "Fixing ownership of named volumes (no-op after first boot)..."
chown -R www-data:www-data /app/storage /data /config 2>/dev/null || true

echo "Running Spora setup..."
php /app/bin/spora spora:setup

# Drop privileges per-program (in supervisord.conf) so PHP processes run as www-data
# while supervisor itself keeps root for /dev/stdout logging.
echo "Setup complete. Starting services..."
exec /usr/bin/supervisord -c /app/supervisord.conf
