<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;
use Plugs\Facades\DB;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();

try {
    $tables = DB::getConnection()->fetchAll("SHOW TABLES");
    $found = false;
    foreach ($tables as $table) {
        $tableName = array_values((array)$table)[0];
        if ($tableName === 'sessions') {
            $found = true;
            break;
        }
    }
    
    if ($found) {
        echo "EXISTS\n";
        $columns = DB::getConnection()->fetchAll("DESCRIBE sessions");
        foreach ($columns as $column) {
            $column = (array)$column;
            echo "- " . ($column['Field'] ?? 'N/A') . " (" . ($column['Type'] ?? 'N/A') . ")\n";
        }
    } else {
        echo "MISSING\n";
    }
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
