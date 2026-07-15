<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

define('BASE_PATH', dirname(__FILE__, 2));

require_once BASE_PATH . '/vendor/autoload.php';

const SPA_INDEX_PATH = __DIR__ . '/spora/index.html';

// Raw path for routing decisions (the dev server strips /spora from
// PATH_INFO when public/spora/index.html exists, so getPathInfo()
// alone isn't reliable under php -S).
$rawPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// / → /spora/ by default; overridable by dropping public/index.html.
if ($rawPath === '/' || $rawPath === '') {
    header('Location: /spora/', true, 301);
    return;
}

// php -S dev server: return false hands static files under public/spora/
// back to php -S for serving. FrankenPHP's try_files does this in prod.
if (PHP_SAPI === 'cli-server' && str_starts_with($rawPath, '/spora/')) {
    $file = __DIR__ . $rawPath;
    if (is_file($file)) {
        return false;
    }
}

// /spora SPA fallback for vue-router client routes (no real file).
if ($rawPath === '/spora' || str_starts_with($rawPath, '/spora/')) {
    if (is_file(SPA_INDEX_PATH)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile(SPA_INDEX_PATH);
        return;
    }
    http_response_code(404);
    return;
}

// Everything else — /api/* and operator-owned paths — flows
// through HttpKernel, which 404s unmatched paths.
$kernel = new HttpKernel();
$kernel->handle($request)->send();
