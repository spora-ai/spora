<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

define('BASE_PATH', dirname(__FILE__, 2));

// Parse the raw request path once. We can't use Symfony's getPathInfo()
// under the dev server because php -S rewrites PATH_INFO when a real
// file exists at public/spora/index.html (see "routing" comment below).
$rawPath = urldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/');

// Dev server (`composer dev`): php -S routes every request through
// this file, so real files under public/spora/ must be streamed here —
// php -S has no equivalent of FrankenPHP's `try_files`. realpath()
// collapses `..` and symlinks; the prefix check rejects escapes outside
// public/. When no real file exists, fall through so the SPA fallback
// below can serve index.html.
if (PHP_SAPI === 'cli-server' && str_starts_with($rawPath, '/spora/')) {
    $inner     = substr($rawPath, 6);
    $publicRoot = realpath(__DIR__);
    $candidate  = $inner !== '' ? realpath(__DIR__ . '/spora/' . $inner) : false;
    if ($publicRoot !== false && $candidate !== false
        && str_starts_with($candidate, $publicRoot . DIRECTORY_SEPARATOR)
        && is_file($candidate)
    ) {
        $ext  = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'js', 'mjs' => 'application/javascript',
            'css'       => 'text/css; charset=UTF-8',
            'html'      => 'text/html; charset=UTF-8',
            'svg'       => 'image/svg+xml',
            'json'      => 'application/json',
            'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico' => 'image/' . $ext,
            default     => 'application/octet-stream',
        };
        header("Content-Type: $mime");
        readfile($candidate);
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

// /spora SPA fallback. FrankenPHP's try_files resolves real files first;
// this only fires for non-existent paths (vue-router client routes).
// Match `/spora` and `/spora/*` so routes without a trailing slash
// (e.g. /spora/agents/123) also hit the SPA. Use $rawPath in cli-server
// mode because php -S strips the /spora prefix from PATH_INFO when
// public/spora/index.html exists (it acts as a "script" for /spora/*).
$sporaPrefix = PHP_SAPI === 'cli-server' ? $rawPath : $path;
if ($sporaPrefix === '/spora') {
    header('Location: /spora/', true, 301);
    return;
}
if ($sporaPrefix === '/spora/' || str_starts_with($sporaPrefix, '/spora/')) {
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
