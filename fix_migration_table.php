<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;
use Plugs\Facades\DB;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();

try {
    echo "Deleting migration entry for 'sessions' table...\n";
    DB::getConnection()->execute("DELETE FROM migrations WHERE migration = '2026_03_01_160501_create_sessions_table'");
    echo "Done.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
