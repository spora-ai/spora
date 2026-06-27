# Installation

Spora ships as three coordinated Composer packages: the framework, the admin UI, and the operator skeleton.

## Standard install (Packagist)

```bash
composer create-project spora-ai/spora my-app
cd my-app
composer install
php bin/spora spora:install
php bin/spora db:seed
composer dev
```

This installs:

- `spora-ai/spora-core` (the framework)
- `spora-ai/spora-frontend` (prebuilt admin UI → `public/dist/`)
- `spora-ai/installer` (Composer plugin that routes the above)

The admin UI is **prebuilt** — no Node toolchain is required on the operator's host.

## Shared-host install (cPanel/FTP)

```bash
# On your dev machine:
composer create-project spora-ai/spora my-app
cd my-app
composer install --no-dev --optimize-autoloader

# Upload the entire `my-app/` directory to your shared host (via FTP or
# cPanel File Manager). Ensure `public/` is the document root.

# On the shared host (SSH or cPanel terminal):
php bin/spora spora:install
php bin/spora db:seed
```

Set the document root to the `public/` directory, not the project root. Update `storage/` to be writable by the web user (typically `chmod -R 775 storage`).

## Development install (HMR)

```bash
git clone https://github.com/spora-ai/spora my-app
cd my-app
git clone https://github.com/spora-ai/spora-frontend ..
composer require spora-ai/spora-frontend --path=../spora-frontend
composer install
php bin/spora spora:install
```

## Development mode

`composer dev` starts the PHP server on `http://localhost:${PHP_PORT:-8080}`.

For full-stack dev (HMR), start Vite in a second terminal:

```bash
# Terminal 1: PHP + Spora API
composer dev

# Terminal 2: Vite dev server (path-installed frontend only)
cd vendor/spora-ai/spora-frontend
npm run dev
```

Vite's `server.proxy['/api']` forwards API calls to PHP. Visit `http://localhost:5173` for HMR; the API lives at `:8080/api/*`.

## Docker install

```bash
docker compose -f docker/docker-compose.yml up
```

The image runs FrankenPHP + supervisord. The prebuilt admin UI is baked in via the `spora-frontend` Composer package (no Node toolchain in the image).

## Troubleshooting

**`public/dist/index.html is missing` after `php bin/spora spora:install`**

This means the frontend package didn't install. Run `composer install spora-ai/spora-frontend` and verify `vendor/spora-ai/installer` is present (it routes the package to `public/dist/`).

**`Permission denied` on `storage/`**

`storage/` must be writable by the web user. On shared hosts: `chmod -R 775 storage`.

**Database errors after deploy**

The first deploy needs `php bin/spora spora:install` to run migrations. Add it to your deploy script.