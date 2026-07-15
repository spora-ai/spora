<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\MimeTypes;

// Entry point: public/ → consumer root via dirname 2 levels up.
define('BASE_PATH', dirname(__FILE__, 2));

require_once BASE_PATH . '/vendor/autoload.php';

/**
 * Stream a file from /public/* with auto-detected MIME. Returns true on
 * success; false if the path resolves outside /public or isn't a regular
 * file. realpath() collapses `..` segments and symlinks; the prefix
 * check guards against traversal escaping.
 */
function servePublicFile(string $absPath): bool
{
    $resolved = realpath($absPath);
    $root     = realpath(__DIR__);
    if ($resolved === false
        || $root === false
        || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)
        || !is_file($resolved)
    ) {
        return false;
    }
    (new BinaryFileResponse(
        $resolved,
        200,
        ['Content-Type' => MimeTypes::getDefault()->guessMimeType($resolved) ?? 'application/octet-stream'],
    ))->prepare(Request::createFromGlobals())->send();

    return true;
}

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// / → /spora/ by default. Operators can disable this redirect by
// removing the block, or by dropping public/index.html (web servers
// serve real files before invoking PHP).
if ($path === '/' || $path === '') {
    header('Location: /spora/', true, 301);
    return;
}

// Spora admin UI: /spora → /spora/ 301; real files served as-is;
// everything else falls back to /spora/index.html so the SPA router
// can handle the path. `strlen($path) === 5 || $path[5] === '/'`
// guards against `/sporafoo` accidentally resolving into this branch.
if ($path === '/spora') {
    header('Location: /spora/', true, 301);
    return;
}
if (str_starts_with($path, '/spora') && (strlen($path) === 5 || $path[5] === '/')) {
    $inner = $path === '/spora/' ? '/index.html' : substr($path, 5);
    if (servePublicFile(__DIR__ . '/spora' . $inner)) {
        return;
    }
    if (is_file(__DIR__ . '/spora/index.html')) {
        (new BinaryFileResponse(__DIR__ . '/spora/index.html', 200,
            ['Content-Type' => 'text/html; charset=UTF-8']))->prepare($request)->send();
        return;
    }
    http_response_code(404);
    return;
}

// Plugin frontends: identity-mapped to public/plugins/<slug>/<path>.
// Plugin bundles ship one main.js; non-asset paths 404 instead of
// leaking the host SPA shell.
if (str_starts_with($path, '/plugins/')) {
    if (servePublicFile(__DIR__ . $path)) {
        return;
    }
    http_response_code(404);
    return;
}

// Everything else — /api/* (incl. /api/health) and the operator's
// reserved paths — flows through the framework. Operators extend
// public/index.php above this line, or drop static files in public/
// for the web server to serve directly.
$kernel = new HttpKernel();
$kernel->handle($request)->send();
