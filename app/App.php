<?php

declare(strict_types=1);

namespace App;

use Spora\Extensions\AbstractExtension;

/**
 * Project-level App extension. Discovered by AppLoader via reflection;
 * one per installation, no manifest, no slug.
 *
 * Override hooks to wire project-local code into the framework:
 *   tools(), drivers(), recipePaths(), schemaVersion(), migrationsPath(),
 *   apps(), register(\DI\ContainerBuilder), routes(), boot().
 *
 * Promote to a plugin later: rename App → Plugin, add plugin.json, ship
 * as a Composer package.
 */
final class App extends AbstractExtension
{
    public function getName(): string
    {
        return 'My Spora App';
    }
}
