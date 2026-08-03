#!/bin/sh
set -e

# Named volumes come up root-owned at runtime regardless of the build-time
# chown in the Dockerfile, so the Caddy BoltDB at /data/caddy/mercure.db and
# FrankenPHP's runtime config at /config are unwritable by www-data until
# we re-chown at boot. Doing this here rather than relying on the Dockerfile
# step avoids a 401-from-BoltDB restart loop on fresh spora_caddy_data
# volumes.
chown -R www-data:www-data /app /data /config

echo "Running Spora setup..."
php /app/bin/spora spora:setup

# The setup command may have created new storage files (database.sqlite,
# secret.key, .schema_stamp, etc.); re-chown so supervisord can read and
# append.
chown -R www-data:www-data /app/storage

echo "Setup complete. Starting services..."
exec /usr/bin/supervisord -c /app/supervisord.conf
