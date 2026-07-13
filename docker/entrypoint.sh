#!/bin/sh
set -e

# /app, /data and /config are root-owned inside the image and the named
# volumes `caddy_data` (mounted on /data) and `caddy_config` (on /config) come
# up root-owned regardless of any build-time chown. The runtime user
# (www-data) has to write to all three:
#   - /app/supervisord.pid
#   - /app/storage, /app/public (Spora runtime files)
#   - /data/caddy/mercure.db (frankenphp native Mercure bolt store)
# Chown on every boot so volumes left root-owned by an earlier failed run
# don't keep breaking the new run.
chown -R www-data:www-data /app /data /config

echo "Running Spora setup..."
php /app/bin/spora spora:setup
chown -R www-data:www-data /app/storage
echo "Setup complete. Starting services..."

# Supervisord runs as root and drops to www-data for the child processes via
# the `user=www-data` directive on each [program:*] section. Running
# supervisord itself as root is necessary because (a) the named volumes'
# mount points only become user-writable after the chown above, and (b) once
# supervisord calls setuid() in the same process, Python's
# `open('/dev/stdout', 'ab')` returns EACCES for the dispatchers' log files
# (the pipe was created by the original root process). Supervisord's own
# log is therefore redirected to a real file; spora-web and spora-worker
# stdout still go to docker logs because the children only *write* to their
# already-open stdout fd, which doesn't require the same permission.
exec /usr/bin/supervisord -c /app/supervisord.conf
