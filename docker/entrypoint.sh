#!/bin/sh
set -e

# Named volumes come up root-owned; bind-mounts may not need this.
echo "Fixing ownership of named volumes (no-op after first boot)..."
chown -R www-data:www-data /app/storage /data /config 2>/dev/null || true

echo "Running Spora setup..."
php /app/bin/spora spora:setup

# Drop privileges here (not in supervisord.conf) so supervisor itself is unprivileged.
echo "Setup complete. Starting services as www-data..."
exec gosu www-data /usr/bin/supervisord -c /app/supervisord.conf
