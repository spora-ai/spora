<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

// Resolve BASE_PATH from this entry point's location. Each entry point
// (public/index.php, bin/spora) is one level below the project root, so
// dirname(__FILE__, 2) gives the consumer's project root. Required for
// the framework to construct its Paths service on the first container
// resolution.
define('BASE_PATH', dirname(__FILE__, 2));

// Dev-server only: let the built-in web server serve real files from
// public/dist/ natively. Apache and FrankenPHP already do this in prod.
if (PHP_SAPI === 'cli-server') {
    $requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    // Decode percent-encoded characters so paths like /foo%20bar.svg
    // resolve to the actual file on disk.
    $file = __DIR__ . '/dist' . urldecode($requestPath);
    if (is_file($file)) {
        return false;
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
