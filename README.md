# Spora Operator Skeleton

A Bedrock-style operator template for [Spora](https://github.com/spora-ai/spora-core), the portable AI agent orchestration platform.

This is what operators `composer create-project` to spin up their own Spora instance on a shared host (cPanel/FTP, VPS, Docker, Kubernetes). It pulls:

- **`spora-ai/spora-core`** — the framework (PHP, plugins, agents, recipes, drivers).
- **`spora-ai/spora-frontend`** — the prebuilt Vue admin UI (delivered as a Composer package, routed to `public/dist/`).
- **`spora-ai/installer`** — the Composer plugin that handles `spora-plugin` and `spora-frontend` package routing.

## Quick start

```bash
composer create-project spora-ai/spora-skeleton my-app
cd my-app

# Install dependencies (this also installs the prebuilt admin UI):
composer install

# Run database migrations:
php bin/spora spora:install

# (Optional) Seed an initial admin user:
php bin/spora db:seed

# Start the dev server:
composer dev
# → PHP on http://localhost:8080 (UI served from public/dist/)
# → Vite on http://localhost:5173 only if you installed spora-frontend via path repo
```

Open <http://localhost:8080> in a browser. Log in with the seeded admin credentials (printed by `db:seed`).

## Documentation

- [docs/13_installation.md](docs/13_installation.md) — install flows (developer + operator)
- [docs/19_operator_guide.md](docs/19_operator_guide.md) — shared-host install, plugin management via UI, backups
- [docs/20_customization.md](docs/20_customization.md) — adding custom tools, agents, recipes

## Plugin management

Plugins ship as Composer packages of type `spora-plugin`. Install via CLI:

```bash
php bin/spora plugin:install spora-ai/spora-plugin-tavily
```

Or via the admin UI at `/apps/plugins` (requires `SPORA_PLUGIN_INSTALL_ENABLED=true`).

## Development mode (HMR)

For frontend development, install `spora-frontend` from a sibling clone instead of Packagist:

```bash
git clone https://github.com/spora-ai/spora-frontend ..
composer require spora-ai/spora-frontend --path=../spora-frontend
composer install
composer dev   # now starts Vite with HMR
```

The skeleton's `bin/dev` auto-detects path installs and starts Vite from `vendor/spora-ai/spora-frontend/`. On dist installs, Vite is skipped — the UI is served as static assets from `public/dist/`.

## License

MIT