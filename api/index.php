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

// 2. Set environment variables using $_ENV and $_SERVER so Laravel's env() helper can read them
$_ENV['VIEW_COMPILED_PATH'] = '/tmp/storage/framework/views';
$_SERVER['VIEW_COMPILED_PATH'] = '/tmp/storage/framework/views';

// 3. Load Composer autoloader
require __DIR__ . '/../vendor/autoload.php';

// 4. Bootstrap the Laravel application
$app = require_once __DIR__ . '/../bootstrap/app.php';

// 5. Override storage and bootstrap cache paths to use writable /tmp directory
$app->useStoragePath('/tmp/storage');
$app->bootstrapPath('/tmp/bootstrap');

// 6. Make the HTTP Kernel and handle the request
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$response = $kernel->handle(
  $request = Illuminate\Http\Request::capture()
)->send();

$kernel->terminate($request, $response);