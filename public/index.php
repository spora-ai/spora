<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

// SPA routing — operator concern: serve dist/index.html for any
// non-/api/ request. The dist is dropped into place by spora-ai/installer
// from the spora-ai/spora-frontend Composer package.
if (!str_starts_with($path, '/api/') && file_exists(__DIR__ . '/dist/index.html')) {
    header('Content-Type: text/html; charset=UTF-8');
    readfile(__DIR__ . '/dist/index.html');
    return;
}

// API request: delegate to the framework's HttpKernel, which encapsulates
// kernel boot, dispatch, and the JSON-500 fallback.
$kernel   = new HttpKernel();
$response = $kernel->handle($request);
$response->send();
