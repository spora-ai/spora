<?php

declare(strict_types=1);

use Spora\Core\HttpKernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__) . '/vendor/autoload.php';

$request = Request::createFromGlobals();
$path    = $request->getPathInfo();

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
