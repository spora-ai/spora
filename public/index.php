<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// Serve static files (CSS/JS/SVG/PNG/etc.) from public/ with proper MIME
// types. The PHP dev server's default behaviour for non-existent files is
// to fall back to public/index.php with PATH_INFO set, so we serve the
// matching file from public/dist/ (or any subdir of public/) BEFORE the
// SPA router intercepts the request as a non-/api/ path.
if ($path !== '/' && $path !== '') {
    $staticFile = __DIR__ . $path;
    $realPath   = realpath($staticFile);
    $publicRoot = realpath(__DIR__);
    // Path-traversal guard: only serve files inside public/.
    if ($realPath !== false && $publicRoot !== false && str_starts_with($realPath, $publicRoot . DIRECTORY_SEPARATOR)) {
        if (is_file($realPath)) {
            $mime = match (strtolower(pathinfo($realPath, PATHINFO_EXTENSION))) {
                'js', 'mjs'  => 'application/javascript',
                'css'        => 'text/css',
                'json'       => 'application/json',
                'svg'        => 'image/svg+xml',
                'png'        => 'image/png',
                'jpg', 'jpeg' => 'image/jpeg',
                'gif'        => 'image/gif',
                'ico'        => 'image/x-icon',
                'woff'       => 'font/woff',
                'woff2'      => 'font/woff2',
                'ttf'        => 'font/ttf',
                'eot'        => 'application/vnd.ms-fontobject',
                'map'        => 'application/json',
                'txt'        => 'text/plain',
                'html'       => 'text/html; charset=UTF-8',
                default      => 'application/octet-stream',
            };
            header('Content-Type: ' . $mime);
            readfile($realPath);
            return;
        }
    }
}

// SPA — serve dist/ for non-API routes.
if (!str_starts_with($path, '/api/') && file_exists(__DIR__ . '/dist/index.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/dist/index.html');
    return;
}

// API — delegate to the framework.
$kernel   = new HttpKernel();
$response = $kernel->handle($request);
$response->send();
