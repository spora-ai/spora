<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

define('BASE_PATH', dirname(__FILE__, 2));

require_once BASE_PATH . '/vendor/autoload.php';

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// / → /spora/ by default; overridable via public/index.html.
if ($path === '/' || $path === '') {
    header('Location: /spora/', true, 301);
    return;
}

// /spora SPA fallback (Apache/dev server have no try_files equivalent;
// FrankenPHP's try_files handles it before this fires).
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

// Everything else — /api/* and operator-owned paths — falls
// through to HttpKernel, which returns its own 404 for unmatched paths.
$kernel = new HttpKernel();
$kernel->handle($request)->send();
