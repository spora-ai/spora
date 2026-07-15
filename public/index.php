<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

define('BASE_PATH', dirname(__FILE__, 2));

// Dev server only. `php -S` auto-serves real files from docroot if the
// router returns false; otherwise the router's output is sent to the
// browser. So: real files under public/spora/ return false to let php -S
// serve them (with auto MIME from ext); missing /spora/* paths fall
// through to the SPA shell. Everything outside /spora/ falls through
// to the normal front-controller logic below.
if (PHP_SAPI === 'cli-server' && str_starts_with($_SERVER['REQUEST_URI'] ?? '/', '/spora')) {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
    if (is_file(__DIR__ . '/spora/index.html')) {
        readfile(__DIR__ . '/spora/index.html');
        return true;
    }
}

require_once BASE_PATH . '/vendor/autoload.php';

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// / → /spora/ by default; overridable via public/index.html.
if ($path === '/' || $path === '') {
    header('Location: /spora/', true, 301);
    return;
}

// /spora SPA fallback for Apache. FrankenPHP's try_files resolves
// real files first; this only fires for non-existent paths.
if ($path === '/spora') {
    header('Location: /spora/', true, 301);
    return;
}
if ($path === '/spora/' || str_starts_with($path, '/spora/')) {
    if (is_file(__DIR__ . '/spora/index.html')) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile(__DIR__ . '/spora/index.html');
        return;
    }
    http_response_code(404);
    return;
}

// Everything else — /api/* and operator-owned paths — falls
// through to HttpKernel, which returns its own 404 for unmatched paths.
$kernel = new HttpKernel();
$kernel->handle($request)->send();
