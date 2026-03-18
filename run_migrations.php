<?php

require __DIR__ . '/vendor/autoload.php';

use Plugs\Bootstrap\Bootstrapper;
use Plugs\Database\Connection;
use Plugs\Database\MigrationRunner;
use Plugs\FeatureModule\FeatureModuleManager;

$bootstrapper = new Bootstrapper(__DIR__);
$app = $bootstrapper->boot();

$connection = Connection::getInstance();
$basePath = database_path('Migrations');

$migrationPaths = [$basePath];

// Include feature module migration paths
$featureManager = FeatureModuleManager::getInstance();
$modulePaths = $featureManager->getMigrationPaths();
$migrationPaths = array_merge($migrationPaths, $modulePaths);

$runner = new MigrationRunner($connection, $migrationPaths);

echo "Running migrations...\n";
$result = $runner->run();

if (empty($result['migrations'])) {
    echo "Nothing to migrate.\n";
} else {
    echo "Successfully ran " . count($result['migrations']) . " migrations:\n";
    foreach ($result['migrations'] as $migration) {
        echo "- " . $migration . "\n";
    }
}
