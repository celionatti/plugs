<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;
use Plugs\Facades\DB;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();

try {
    $migrations = DB::getConnection()->fetchAll("SELECT * FROM migrations");
    echo "MIGRATIONS IN TABLE:\n";
    foreach ($migrations as $m) {
        $m = (array)$m;
        echo "- " . ($m['migration'] ?? 'N/A') . " (Batch: " . ($m['batch'] ?? 'N/A') . ")\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
