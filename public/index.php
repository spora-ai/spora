<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

// Entry point: public/ → consumer root via dirname 2 levels up.
define('BASE_PATH', dirname(__FILE__, 2));

require_once BASE_PATH . '/vendor/autoload.php';

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// Root → /spora/ by default. Operators can disable by removing this
// block, or by dropping public/index.html (the web server serves it
// before invoking PHP).
if ($path === '/' || $path === '') {
    header('Location: /spora/', true, 301);
    return;
}

// Spora admin UI: /spora → /spora/ 301; SPA client routes
// (e.g. /spora/login) fall back to the SPA shell. Real /spora/* files
// are served directly by the web server (Apache .htaccess rule 1,
// FrankenPHP /spora/* handler, PHP built-in dev server), so this
// branch only fires for non-existent paths.
if ($path === '/spora') {
    header('Location: /spora/', true, 301);
    return;
}
if (str_starts_with($path, '/spora/')) {
    if (is_file(__DIR__ . '/spora/index.html')) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile(__DIR__ . '/spora/index.html');
        return;
    }
    http_response_code(404);
    return;
}

// Plugin frontends: identity-mapped to public/plugins/<slug>/<path>.
// Real files are served directly by the web server; non-existent
// paths 404 here instead of leaking the host SPA shell.
if (str_starts_with($path, '/plugins/')) {
    http_response_code(404);
    return;
}

// Everything else — /api/* (incl. /api/health) and operator-owned
// paths — flows through the framework.
$kernel = new HttpKernel();
$kernel->handle($request)->send();
