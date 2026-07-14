<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

// Entry point: public/ → consumer root via dirname 2 levels up.
define('BASE_PATH', dirname(__FILE__, 2));

// Dev-server only: serve real files natively. Apache and FrankenPHP
// already do this in prod (root * /app/public in docker/frankenphp.conf).
// Two on-disk trees hold the static files this app serves:
//   public/dist/    — host SPA bundles (hashed assets under /assets/, favicon, etc.)
//   public/plugins/ — spora-plugin-frontend packages routed by spora-installer
// URL `/plugins/<slug>/<path>` is an identity mapping to the on-disk
// `public/plugins/<slug>/<path>`; everything else under public/dist/ has
// the URL prepended with `/dist` to find the bundled SPA files.
// We can't just `return false` and let PHP's built-in dev server serve
// the file — it only handles files inside its doc root, and `/dist/*`
// lives outside when the doc root is `public/`. We `readfile()` and
// `return true` so PHP uses our streamed bytes as the response.
// Anything else falls through to PHP routing (API or SPA index).
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    $decoded     = urldecode($requestPath);
    if ($decoded !== '/' && str_starts_with($decoded, '/')) {
        // Pin the public/ root once per request. `realpath()` collapses
        // any symlinks on the way to __DIR__ so the prefix check below
        // is a literal byte comparison against the resolved root — no
        // chance of a `/../` or symlink escape serving files outside
        // public/.
        $publicRoot = realpath(__DIR__);
        $candidates = [__DIR__ . $decoded, __DIR__ . '/dist' . $decoded];
        foreach ($candidates as $candidate) {
            // Resolve through `..` segments and symlinks; reject anything
            // that ends up outside $publicRoot before we ever call
            // readfile().
            $resolved = realpath($candidate);
            if ($resolved === false) {
                continue;
            }
            if (!str_starts_with($resolved, $publicRoot . DIRECTORY_SEPARATOR)) {
                continue;
            }
            if (!is_file($resolved)) {
                continue;
            }
            $ext = strtolower(pathinfo($resolved, PATHINFO_EXTENSION));
            $mime = match ($ext) {
                'js', 'mjs'  => 'application/javascript',
                'css'         => 'text/css; charset=UTF-8',
                'svg'         => 'image/svg+xml',
                'json'        => 'application/json',
                'html'        => 'text/html; charset=UTF-8',
                'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico' => 'image/' . ($ext === 'svg' ? 'svg+xml' : $ext),
                default       => 'application/octet-stream',
            };
            header("Content-Type: $mime");
            readfile($resolved);
            return true;
        }
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// SPA — serve dist/index.html for non-API routes.
if (!str_starts_with($path, '/api/') && file_exists(__DIR__ . '/dist/index.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/dist/index.html');
    return;
}

// API — delegate to the framework.
$kernel   = new HttpKernel();
$response = $kernel->handle($request);
$response->send();
