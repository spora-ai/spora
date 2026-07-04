# Operator Guide

Day-2 operations for a Spora install on shared hosting.

## Backups

The only stateful directory is `storage/`. Backup:

- `storage/database.sqlite` — SQLite database (the entire app state).
- `storage/.plugins_stamp` — plugin loader cache.
- `config.php` and `.env` — your operator configuration.

For MySQL: dump via `mysqldump`. The connection string lives in `.env` (`SPORA_DB_*`).

## Plugin management

Install plugins via the admin UI at `/apps/plugins` (admin role required). The UI calls `POST /api/v1/plugins`. Enable it with:

```bash
# In .env:
SPORA_PLUGIN_INSTALL_ENABLED=true
```

Or via CLI. Package names follow the `spora-ai/spora-plugin-<name>` pattern; pin a constraint with `--constraint=^<major>.<minor>`:

```bash
php bin/spora spora:plugin:install spora-ai/spora-plugin-tavily --constraint=^0.2
php bin/spora spora:plugin:list
php bin/spora spora:plugin:uninstall spora-ai/spora-plugin-tavily
php bin/spora spora:plugin:update spora-ai/spora-plugin-tavily
```

Plugins land in `plugins/<name>/` (routed by `spora-ai/installer`). Each plugin owns its own migrations, tools, and assets.

The Web UI install path (`POST /api/v1/plugins`, request/response shapes, idempotency rules, error codes) is documented in the framework release that ships with v0.6.1: see [plugin install API](https://github.com/spora-ai/spora-core/blob/main/docs/20_plugin_install_api.md).

## Updating the framework

```bash
composer update spora-ai/spora-core
php bin/spora spora:install   # apply any new migrations
```

Test the upgrade on a staging copy first.

## Updating the admin UI

```bash
composer update spora-ai/spora-frontend
# public/dist/ is replaced in place; no migrations needed
```

## Logs

- `storage/spora.log` — application log (PSR-3, Monolog).
- `storage/php.log` — PHP errors (from `bin/dev`'s PHP server, when applicable).

Tail with `tail -f storage/spora.log`.

## Cron workers (scheduled tasks)

Spora has two worker modes:

- **Sync** (`SPORA_SYNC_MODE=true`): inline — agent runs in the same request as the user. Default for dev.
- **Async** (`SPORA_SYNC_MODE=false`): queued — requires `php bin/spora worker:run` to drain the queue.

For scheduled tasks, run `php bin/spora worker:run --scheduled` via cron every minute:

```cron
* * * * * cd /path/to/app && php bin/spora worker:run --scheduled >> storage/spora.log 2>&1
```

## Reset

Wipe state for a fresh start:

```bash
php bin/spora db:reset --force   # drops the database, clears schema stamp
rm -rf plugins/*                  # uninstalls all plugins
composer install                  # reinstall frontend assets
php bin/spora spora:install
php bin/spora db:seed
```

## File permissions

```bash
chmod -R 775 storage
chmod -R 755 bin public
```

On shared hosts, the web user (e.g. `nobody`, `www-data`) needs write access to `storage/`.