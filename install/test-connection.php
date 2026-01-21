<?php

declare(strict_types=1);

/**
 * Test Database Connection
 * 
 * AJAX endpoint for testing database connection.
 */

header('Content-Type: application/json');

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$driver = $_POST['db_driver'] ?? 'mysql';
$host = $_POST['db_host'] ?? 'localhost';
$port = (int) ($_POST['db_port'] ?? 3306);
$database = $_POST['db_database'] ?? '';
$username = $_POST['db_username'] ?? '';
$password = $_POST['db_password'] ?? '';

try {
    if ($driver === 'sqlite') {
        $dbPath = dirname(__DIR__) . '/storage/database.sqlite';

        // Create storage directory if needed
        $storageDir = dirname($dbPath);
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0755, true);
        }

        $dsn = 'sqlite:' . $dbPath;
        $pdo = new PDO($dsn);
    } elseif ($driver === 'pgsql') {
        if (empty($database)) {
            throw new Exception('Database name is required for PostgreSQL');
        }
        $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
        $pdo = new PDO($dsn, $username, $password);
    } else {
        if (empty($database)) {
            throw new Exception('Database name is required');
        }
        $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Try a simple query to verify connection
    $pdo->query('SELECT 1');

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
