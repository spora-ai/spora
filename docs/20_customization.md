# Customization

How to extend a Spora install with custom tools, agents, and recipes.

## Custom tools

Tools are PHP classes implementing `ToolInterface`. Four ways to ship them:

### 1. As a plugin (recommended for reusable tools)

Scaffold from `spora-ai/spora-plugin-skeleton` (the plugin-author template):

```bash
# Use the GitHub "Use this template" button, then:
composer create-project sspa-ai/spora-plugin-skeleton my-tool
# Or clone: git clone https://github.com/spora-ai/spora-plugin-skeleton my-tool
```

Edit the generated tool class, commit, tag a release, push to GitHub. Then in your operator install, prefer the CLI install command (it handles routing + caches + migrations in one step):

```bash
php bin/spora spora:plugin:install my-vendor/my-tool --constraint=^0.1
```

`composer require` also works (the `spora-ai/installer` Composer plugin routes `spora-plugin`-typed packages into `plugins/<name>/`), but the CLI path is the operator-facing canonical install. See [Spora framework docs — plugin author guide](../vendor/spora-ai/spora-core/docs/18_plugin_author_guide.md) for the plugin package shape and `type: spora-plugin` requirement.

### 2. As an App extension (recommended for project-local code)

The Symfony-style approach: drop a class in `app/App.php` (shipped with this
skeleton as a stub) and override any hook. The framework discovers the file
via reflection — no manifest, no slug, no `composer require`.

```php
// app/App.php
final class App extends \Spora\Extensions\AbstractExtension
{
    public function getName(): string { return 'My Spora App'; }

    public function tools(): array { return [Tools\MyTool::class]; }
}
```

**Status:** the App extension surface lands in `spora-ai/spora-core` v0.5.
The skeleton's `composer.json` currently pins `^0.4` (latest stable) and
the shipped `app/App.php` is a dormant stub — it does nothing until you
upgrade to `^0.5` or copy the new framework code over. The
`phpstan.neon`, `composer lint`, `composer analyse` scripts are wired up
locally, so when you upgrade, the scaffold is ready to use.

When to migrate App → Plugin: when you need to ship the same code to
multiple Spora installs. Renaming `App.php` → `Plugin.php` plus a
`plugin.json` is the entire migration.

Full reference: see [Spora framework docs — `docs/08_app_extension.md`](../vendor/spora-ai/spora-core/docs/08_app_extension.md).

### 3. In-app (for one-off tools)

Drop a PHP class into your project's `app/Tools/` (or any PSR-4 path you autoload), implement `ToolInterface`, and register it via `ToolConfigService`. Prefer the App extension above — this path is for the rare case where you don't want a single App entry-point at all.

### 4. Fork the skeleton

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