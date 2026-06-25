# Customization

How to extend a Spora install with custom tools, agents, and recipes.

## Custom tools

Tools are PHP classes implementing `ToolInterface`. Three ways to ship them:

### 1. As a plugin (recommended for reusable tools)

Scaffold from `spora-ai/spora-plugin-skeleton` (the plugin-author template):

```bash
# Use the GitHub "Use this template" button, then:
composer create-project sspa-ai/spora-plugin-skeleton my-tool
# Or clone: git clone https://github.com/spora-ai/spora-plugin-skeleton my-tool
```

Edit the generated tool class, commit, tag a release, push to GitHub. Then in your operator install:

```bash
composer require my-vendor/my-tool
```

### 2. In-app (for one-off tools)

Drop a PHP class into your project's `app/Tools/` (or any PSR-4 path you autoload), implement `ToolInterface`, and register it via `ToolConfigService`.

### 3. Fork the skeleton

If your customization is tightly coupled to the operator project, fork the skeleton, edit the files in place, and rebuild the Docker image.

## Custom agents

Agents are Eloquent models. Create via the admin UI at `/apps/agents`, or programmatically:

```php
$agent = new Agent([
    'name' => 'Researcher',
    'system_prompt' => 'You are a research assistant...',
    'driver' => 'anthropic',
    'model' => 'claude-sonnet-4-6',
]);
$agent->save();
```

## Custom recipes

Recipes are YAML files in `recipes/`. Schema: see `docs/01_architecture.md`. Drop a new YAML file in, then refresh the recipe list (admin UI or CLI).

## Theming the admin UI

The admin UI is a prebuilt Composer package. To customize:

1. Fork `spora-ai/spora-frontend`.
2. Modify the Vue components.
3. Build: `npm run build`.
4. Install your fork as a path repo:

   ```bash
   composer require spora-ai/spora-frontend --path=../my-frontend-fork
   composer install
   ```

5. Commit your fork + skeleton's `composer.json` updates.

## Environment variables

All configuration is `.env`-driven. See `.env.example` for the full list. Key vars:

- `SPORA_DB_*` — database connection (SQLite or MySQL).
- `SPORA_MERCURE_URL` — Mercure hub URL for real-time UI updates.
- `SPORA_SYNC_MODE` — `true` for inline (dev), `false` for queued (prod).
- `SPORA_PLUGIN_INSTALL_ENABLED` — `true` to expose the install-via-UI feature.
- `SPORA_FRONTEND_DEV` — `1` to force Vite HMR mode in `bin/dev`.