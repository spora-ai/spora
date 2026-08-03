#!/bin/sh
set -e

# Named volumes come up root-owned at runtime regardless of the build-time
# chown in the Dockerfile, so re-apply for the runtime-mount paths to
# avoid a BoltDB EACCES restart loop on fresh spora_caddy_data volumes.
chown -R www-data:www-data /app /data /config

echo "Running Spora setup..."
php /app/bin/spora spora:setup

# Setup may have created new files in /app/storage (database.sqlite,
# secret.key, .schema_stamp, …); re-chown so supervisord can append.
chown -R www-data:www-data /app/storage

echo "Setup complete. Starting services..."
exec /usr/bin/supervisord -c /app/supervisord.conf
