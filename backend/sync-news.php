<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$scraper = app(App\Services\ForexFactoryScraper::class);
$result = $scraper->syncWeek();
echo 'Sync Week Result:' . PHP_EOL;
echo 'Success: ' . ($result['success'] ? 'Yes' : 'No') . PHP_EOL;
echo 'Synced: ' . $result['synced'] . PHP_EOL;
echo 'Updated: ' . $result['updated'] . PHP_EOL;
echo 'Dates processed: ' . implode(', ', $result['dates_processed'] ?? []) . PHP_EOL;
