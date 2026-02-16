<?php

// 1. Pastikan folder temporer tersedia untuk Laravel
$storageFolders = [
  '/tmp/storage/framework/views',
  '/tmp/storage/framework/cache',
  '/tmp/storage/framework/sessions',
  '/tmp/storage/bootstrap/cache',
];

foreach ($storageFolders as $folder) {
  if (!is_dir($folder)) {
    mkdir($folder, 0755, true);
  }
}

// 2. Override path storage dan cache ke /tmp (karena Vercel read-only)
putenv('VIEW_COMPILED_PATH=/tmp/storage/framework/views');
putenv('CACHE_DIRECTORY=/tmp/storage/framework/cache');

require __DIR__ . '/../public/index.php';