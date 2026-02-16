<?php

// Forward the request to the Laravel application for Vercel deployment.

// 1. Create required temporary storage directories (Vercel filesystem is read-only except /tmp)
$storageFolders = [
  '/tmp/storage/framework/views',
  '/tmp/storage/framework/cache',
  '/tmp/storage/framework/cache/data',
  '/tmp/storage/framework/sessions',
  '/tmp/storage/framework/testing',
  '/tmp/storage/logs',
  '/tmp/bootstrap/cache',
];

foreach ($storageFolders as $folder) {
  if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
  }
}

// 2. Set environment variables BEFORE bootstrapping Laravel
//    Uses $_ENV and $_SERVER so Laravel's env() helper can read them.
//    These override the default bootstrap/cache paths to writable /tmp locations.
$envOverrides = [
  'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
  'APP_SERVICES_CACHE' => '/tmp/bootstrap/cache/services.php',
  'APP_PACKAGES_CACHE' => '/tmp/bootstrap/cache/packages.php',
  'APP_CONFIG_CACHE' => '/tmp/bootstrap/cache/config.php',
  'APP_ROUTES_CACHE' => '/tmp/bootstrap/cache/routes-v7.php',
  'APP_EVENTS_CACHE' => '/tmp/bootstrap/cache/events.php',
  'LOG_CHANNEL' => 'stderr',
  'SESSION_DRIVER' => 'array',
  'CACHE_DRIVER' => 'array',
];

foreach ($envOverrides as $key => $value) {
  $_ENV[$key] = $value;
  $_SERVER[$key] = $value;
}

// 3. Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// 4. Bootstrap the Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';

// 5. Override storage path to writable /tmp directory
$app->useStoragePath('/tmp/storage');

// 6. Make the HTTP Kernel and handle the request
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
  $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);