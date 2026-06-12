<?php

declare(strict_types=1);

namespace Plugs\Console\Commands;

use Plugs\Console\Command;
use Plugs\Console\Support\Filesystem;

class AppInstallCommand extends Command
{
    protected string $description = 'Install Plugs Framework applications via CLI';

    protected function defineOptions(): array
    {
        return [
            '--db-driver' => 'Database driver (mysql, pgsql, sqlite)',
            '--db-host' => 'Database host name',
            '--db-port' => 'Database port number',
            '--db-database' => 'Database name or sqlite file path',
            '--db-username' => 'Database username',
            '--db-password' => 'Database password',
            '--app-name' => 'Application name',
            '--app-url' => 'Application URL',
            '--app-env' => 'Application environment (local, production, testing)',
            '--app-timezone' => 'Application timezone',
            '--admin-name' => 'Administrator name',
            '--admin-email' => 'Administrator email address',
            '--admin-password' => 'Administrator password',
            '--no-interaction' => 'Run the installer without prompting',
        ];
    }

    public function handle(): int
    {
        $this->checkpoint('start');
        $this->advancedHeader('Plugs Installer CLI', 'Initialize and configure a new Plugs Framework application');

        if (Filesystem::exists(BASE_PATH . 'plugs.lock')) {
            $this->error("Application already installed (plugs.lock exists).");
            return self::FAILURE;
        }

        // Load installer config
        $configPath = BASE_PATH . 'public/install/config.php';
        if (!Filesystem::exists($configPath)) {
            $this->error("Installer configuration file not found at: {$configPath}");
            return self::FAILURE;
        }

        $config = require $configPath;

        // Step 1: System requirements check
        $this->section('System Requirements Check');
        $passed = true;
        
        // Check PHP Version
        $requiredPhp = $config['php_version'];
        $phpPassed = version_compare(PHP_VERSION, $requiredPhp, '>=');
        if ($phpPassed) {
            $this->success("PHP version: " . PHP_VERSION . " (Required: {$requiredPhp}+)");
        } else {
            $this->error("PHP version: " . PHP_VERSION . " (Required: {$requiredPhp}+)");
            $passed = false;
        }

        // Check required extensions
        foreach ($config['extensions'] as $ext => $name) {
            if (extension_loaded($ext)) {
                $this->success("Extension loaded: {$name} ({$ext})");
            } else {
                $this->error("Missing extension: {$name} ({$ext})");
                $passed = false;
            }
        }

        // Check writable directories
        foreach ($config['writable_directories'] as $dir) {
            $path = BASE_PATH . $dir;
            $dirPassed = false;
            if (file_exists($path)) {
                $dirPassed = is_writable($path);
            } else {
                $parent = dirname($path);
                while (!file_exists($parent) && $parent !== dirname($parent)) {
                    $parent = dirname($parent);
                }
                $dirPassed = is_writable($parent);
            }
            if ($dirPassed) {
                $this->success("Directory writable: {$dir}");
            } else {
                $this->error("Directory not writable: {$dir}");
                $passed = false;
            }
        }

        if (!$passed) {
            $this->error("System requirements check failed. Installation aborted.");
            return self::FAILURE;
        }

        // Step 2: Database Configuration
        $dbDriver = $this->option('db-driver');
        $dbHost = $this->option('db-host');
        $dbPort = $this->option('db-port');
        $dbDatabase = $this->option('db-database');
        $dbUsername = $this->option('db-username');
        $dbPassword = $this->option('db-password');

        if (!$this->hasOption('no-interaction')) {
            $this->section('Database Configuration');
            $dbDriver = $this->choice('Select database driver', ['mysql', 'pgsql', 'sqlite'], $dbDriver ?? 'mysql');
            
            if ($dbDriver !== 'sqlite') {
                $dbHost = $this->ask('Database host', $dbHost ?? 'localhost');
                $dbPort = (int)$this->ask('Database port', (string)($dbPort ?? ($dbDriver === 'pgsql' ? 5432 : 3306)));
                $dbDatabase = $this->ask('Database name', $dbDatabase ?? 'plugs');
                $dbUsername = $this->ask('Database username', $dbUsername ?? 'root');
                $dbPassword = $this->secret('Database password');
            } else {
                $dbDatabase = $this->ask('SQLite database path relative to storage/', $dbDatabase ?? 'database.sqlite');
            }
        } else {
            $dbDriver = $dbDriver ?? 'mysql';
            $dbHost = $dbHost ?? 'localhost';
            $dbPort = (int)($dbPort ?? ($dbDriver === 'pgsql' ? 5432 : 3306));
            $dbDatabase = $dbDatabase ?? 'plugs';
            $dbUsername = $dbUsername ?? 'root';
            $dbPassword = $dbPassword ?? '';
        }

        $connectionPassed = false;
        while (!$connectionPassed) {
            $dbConfig = [
                'driver'   => $dbDriver,
                'host'     => $dbHost,
                'port'     => (int)$dbPort,
                'database' => $dbDatabase,
                'username' => $dbUsername,
                'password' => $dbPassword,
            ];

            $testResult = $this->testPdoConnection($dbConfig);
            if ($testResult['success']) {
                $this->success("Database connection successful!");
                $connectionPassed = true;
            } else {
                $this->error("Database connection failed: " . $testResult['error']);
                if ($this->hasOption('no-interaction')) {
                    return self::FAILURE;
                }
                
                if (!$this->confirm("Would you like to modify database connection settings?", true)) {
                    $this->error("Installation cancelled.");
                    return self::FAILURE;
                }
                
                $dbDriver = $this->choice('Select database driver', ['mysql', 'pgsql', 'sqlite'], $dbDriver);
                if ($dbDriver !== 'sqlite') {
                    $dbHost = $this->ask('Database host', $dbHost);
                    $dbPort = (int)$this->ask('Database port', (string)$dbPort);
                    $dbDatabase = $this->ask('Database name', $dbDatabase);
                    $dbUsername = $this->ask('Database username', $dbUsername);
                    $dbPassword = $this->secret('Database password');
                } else {
                    $dbDatabase = $this->ask('SQLite database path relative to storage/', $dbDatabase);
                }
            }
        }

        // Step 3: Application Settings
        $appName = $this->option('app-name');
        $appUrl = $this->option('app-url');
        $appEnv = $this->option('app-env');
        $appTimezone = $this->option('app-timezone');

        if (!$this->hasOption('no-interaction')) {
            $this->section('Application Settings');
            $appName = $this->ask('Application name', $appName ?? 'Plugs App');
            $appUrl = $this->ask('Application URL', $appUrl ?? 'http://localhost');
            $appEnv = $this->choice('Application environment', ['local', 'production', 'testing'], $appEnv ?? 'local');
            
            $timezones = array_keys($config['timezones']);
            $appTimezone = $this->choice('Application timezone', $timezones, array_search($appTimezone ?? 'UTC', $timezones) ?: 0);
        } else {
            $appName = $appName ?? 'Plugs App';
            $appUrl = $appUrl ?? 'http://localhost';
            $appEnv = $appEnv ?? 'local';
            $appTimezone = $appTimezone ?? 'UTC';
        }

        // Step 4: Admin Account
        $adminName = $this->option('admin-name');
        $adminEmail = $this->option('admin-email');
        $adminPassword = $this->option('admin-password');

        if (!$this->hasOption('no-interaction')) {
            $this->section('Administrator Account');
            $adminName = $this->ask('Admin name', $adminName ?? 'Admin');
            
            $emailPassed = false;
            while (!$emailPassed) {
                $adminEmail = $this->ask('Admin email address', $adminEmail ?? 'admin@example.com');
                if (filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $emailPassed = true;
                } else {
                    $this->error("Invalid email address.");
                }
            }

            $passPassed = false;
            while (!$passPassed) {
                $adminPassword = $this->secret('Admin password (min 8 characters)');
                if (strlen($adminPassword) >= 8) {
                    $confirmPassword = $this->secret('Confirm admin password');
                    if ($adminPassword === $confirmPassword) {
                        $passPassed = true;
                    } else {
                        $this->error("Passwords do not match.");
                    }
                } else {
                    $this->error("Password must be at least 8 characters.");
                }
            }
        } else {
            $adminName = $adminName ?? 'Admin';
            $adminEmail = $adminEmail ?? 'admin@example.com';
            $adminPassword = $adminPassword ?? 'secret123';
            
            if (!filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                $this->error("Invalid admin email address provided.");
                return self::FAILURE;
            }
            if (strlen($adminPassword) < 8) {
                $this->error("Admin password must be at least 8 characters.");
                return self::FAILURE;
            }
        }

        $installData = [
            'database' => [
                'driver' => $dbDriver,
                'host' => $dbHost,
                'port' => (int)$dbPort,
                'database' => $dbDatabase,
                'username' => $dbUsername,
                'password' => $dbPassword,
            ],
            'app' => [
                'name' => $appName,
                'url' => rtrim($appUrl, '/'),
                'env' => $appEnv,
                'timezone' => $appTimezone,
                'key' => 'base64:' . base64_encode(random_bytes(32)),
            ],
            'admin' => [
                'name' => $adminName,
                'email' => $adminEmail,
                'password' => password_hash($adminPassword, PASSWORD_DEFAULT),
            ]
        ];

        // Final summary and confirmation
        if (!$this->hasOption('no-interaction')) {
            $this->section('Installation Summary');
            $this->commandSummary('Install Settings', [
                'App Name' => $appName,
                'App URL' => $appUrl,
                'App Environment' => $appEnv,
                'App Timezone' => $appTimezone,
                'Database Driver' => $dbDriver,
                'Database Host' => $dbDriver === 'sqlite' ? 'N/A' : $dbHost . ':' . $dbPort,
                'Database Name' => $dbDatabase,
                'Admin Name' => $adminName,
                'Admin Email' => $adminEmail,
            ]);

            if (!$this->confirm("Proceed with installation?", true)) {
                $this->error("Installation cancelled.");
                return self::FAILURE;
            }
        }

        // Install execution
        $this->section('Installing Plugs Framework');

        // Task 1: Create directories
        $this->timeline(1, 6, "Creating directories...");
        foreach ($config['directories'] as $dir) {
            $path = BASE_PATH . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }
        $gitkeepDirs = ['storage/logs', 'storage/views', 'storage/framework', 'database/migrations'];
        foreach ($gitkeepDirs as $dir) {
            $path = BASE_PATH . $dir . '/.gitkeep';
            if (!file_exists($path)) {
                file_put_contents($path, '');
            }
        }
        usleep(100000);

        // Task 2: Generate application files
        $this->timeline(2, 6, "Generating application files...");
        $templatesPath = BASE_PATH . 'public/install/templates/';
        foreach ($config['files'] as $targetFile => $templateFile) {
            $templatePath = $templatesPath . $templateFile;
            $targetPath = BASE_PATH . $targetFile;

            if (!file_exists($templatePath)) {
                $this->error("Template not found: {$templateFile}");
                return self::FAILURE;
            }

            $content = file_get_contents($templatePath);
            $content = $this->replacePlaceholders($content, $installData);

            $parentDir = dirname($targetPath);
            if (!is_dir($parentDir)) {
                mkdir($parentDir, 0755, true);
            }

            file_put_contents($targetPath, $content);
            if ($targetFile === 'theplugs') {
                @chmod($targetPath, 0755);
            }
        }
        usleep(100000);

        // Task 3: Copy binary files
        $this->timeline(3, 6, "Copying assets...");
        if (isset($config['binary_files'])) {
            foreach ($config['binary_files'] as $targetFile => $sourceFile) {
                $sourcePath = BASE_PATH . 'public/install/' . $sourceFile;
                $targetPath = BASE_PATH . $targetFile;

                if (file_exists($sourcePath)) {
                    $parentDir = dirname($targetPath);
                    if (!is_dir($parentDir)) {
                        mkdir($parentDir, 0755, true);
                    }
                    copy($sourcePath, $targetPath);
                }
            }
        }
        usleep(100000);

        // Task 4: Setup Database Tables
        $this->timeline(4, 6, "Creating database tables...");
        try {
            if ($dbDriver === 'sqlite') {
                $dsn = 'sqlite:' . BASE_PATH . 'storage/' . $dbDatabase;
                $pdo = new \PDO($dsn);
            } elseif ($dbDriver === 'pgsql') {
                $dsn = "pgsql:host={$dbHost};port={$dbPort};dbname={$dbDatabase}";
                $pdo = new \PDO($dsn, $dbUsername, $dbPassword);
            } else {
                $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbDatabase};charset=utf8mb4";
                $pdo = new \PDO($dsn, $dbUsername, $dbPassword);
            }

            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $this->createUsersTable($pdo, $dbDriver);
            $this->createSessionsTable($pdo, $dbDriver);
            $this->createMigrationsTable($pdo, $dbDriver);
            $this->createJobsTable($pdo, $dbDriver);
        } catch (\PDOException $e) {
            $this->error("Database setup failed: " . $e->getMessage());
            return self::FAILURE;
        }
        usleep(100000);

        // Task 5: Create admin user
        $this->timeline(5, 6, "Creating admin account...");
        try {
            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', ?)");
            $stmt->execute([
                $adminName,
                $adminEmail,
                $installData['admin']['password'],
                date('Y-m-d H:i:s'),
            ]);
        } catch (\PDOException $e) {
            $this->error("Failed to create admin user: " . $e->getMessage());
            return self::FAILURE;
        }
        usleep(100000);

        // Task 6: Finalize installation
        $this->timeline(6, 6, "Finalizing installation...");
        $lockContent = json_encode([
            'installed' => true,
            'version' => '1.0.0',
            'framework' => 'Plugs Framework',
            'installed_at' => date('Y-m-d H:i:s'),
            'installed_by' => $adminEmail,
            'php_version' => PHP_VERSION,
            'environment' => $appEnv,
            'app_name' => $appName,
            'database_driver' => $dbDriver,
            'checksum' => hash('sha256', serialize($installData) . time()),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        file_put_contents(BASE_PATH . 'plugs.lock', $lockContent);
        usleep(100000);

        $this->newLine();

        // Run Composer Install
        $composerPath = $this->findComposer();
        if ($composerPath) {
            $this->info("Composer found. Running 'composer install' to synchronize dependencies...");
            $rootPath = rtrim(BASE_PATH, '/\\');
            $this->callComposer($composerPath, $rootPath);
        } else {
            $this->warning("Composer not found in system PATH. Please run 'composer install' manually to download dependencies.");
        }

        $this->newLine();
        $this->box(
            "Plugs Framework has been successfully installed!\n\n" .
            "Admin URL: " . $appUrl . "/admin\n" .
            "Credentials: " . $adminEmail,
            "🎉 Installation Complete!",
            "success"
        );

        $this->checkpoint('finished');
        if ($this->isVerbose()) {
            $this->displayTimings();
        }

        return self::SUCCESS;
    }

    private function testPdoConnection(array $config): array
    {
        try {
            $driver = $config['driver'] ?? 'mysql';
            $host = $config['host'] ?? 'localhost';
            $port = (int)($config['port'] ?? 3306);
            $database = $config['database'] ?? '';
            $username = $config['username'] ?? '';
            $password = $config['password'] ?? '';

            if ($driver === 'sqlite') {
                $dsn = "sqlite:" . BASE_PATH . "storage/" . $database;
                $pdo = new \PDO($dsn);
            } elseif ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
                $pdo = new \PDO($dsn, $username, $password);
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
                $pdo = new \PDO($dsn, $username, $password);
            }

            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $pdo->query('SELECT 1');

            return ['success' => true];
        } catch (\PDOException $e) {
            if (($config['driver'] ?? 'mysql') === 'mysql' && $e->getCode() == 1049) {
                return $this->tryCreateDatabase($config);
            }

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function tryCreateDatabase(array $config): array
    {
        try {
            $host = $config['host'] ?? 'localhost';
            $port = (int)($config['port'] ?? 3306);
            $database = $config['database'] ?? '';
            $username = $config['username'] ?? '';
            $password = $config['password'] ?? '';

            $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
            $pdo = new \PDO($dsn, $username, $password);
            $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $database = preg_replace('/[^a-zA-Z0-9_\-]/', '', $database);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

            return ['success' => true];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => "The database could not be found and auto-creation failed: " . $e->getMessage() . ". Please create it manually."
            ];
        }
    }

    private function replacePlaceholders(string $content, array $installData): string
    {
        $replacements = [
            '{{APP_NAME}}' => $installData['app']['name'] ?? 'Plugs Framework',
            '{{APP_FRAMEWORK_NAME}}' => str_replace(" ", "_", strtolower($installData['app']['name'])) ?? 'plugs',
            '{{APP_URL}}' => $installData['app']['url'] ?? 'http://localhost',
            '{{APP_ENV}}' => $installData['app']['env'] ?? 'local',
            '{{APP_TIMEZONE}}' => $installData['app']['timezone'] ?? 'UTC',
            '{{APP_KEY}}' => $installData['app']['key'],
            '{{APP_DEBUG}}' => ($installData['app']['env'] ?? 'local') === 'local' ? 'true' : 'false',
            '{{DB_DRIVER}}' => $installData['database']['driver'] ?? 'mysql',
            '{{DB_HOST}}' => $installData['database']['host'] ?? 'localhost',
            '{{DB_PORT}}' => (string) ($installData['database']['port'] ?? 3306),
            '{{DB_DATABASE}}' => $installData['database']['database'] ?? 'plugs',
            '{{DB_USERNAME}}' => $installData['database']['username'] ?? 'root',
            '{{DB_PASSWORD}}' => $installData['database']['password'] ?? '',
            '{{GENERATED_DATE}}' => date('Y-m-d H:i:s'),
            '{{YEAR}}' => date('Y'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function createUsersTable(\PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT DEFAULT 'user',
                email_verified_at TEXT NULL,
                remember_token TEXT NULL,
                last_login_at TEXT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )";
        } elseif ($driver === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) DEFAULT 'user',
                email_verified_at TIMESTAMP NULL,
                remember_token VARCHAR(100) NULL,
                last_login_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) DEFAULT 'user',
                email_verified_at TIMESTAMP NULL,
                remember_token VARCHAR(100) NULL,
                last_login_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        $pdo->exec($sql);
    }

    private function createSessionsTable(\PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS sessions (
                id TEXT PRIMARY KEY,
                user_id INTEGER NULL,
                ip_address TEXT NULL,
                user_agent TEXT NULL,
                payload TEXT NOT NULL,
                last_activity INTEGER NOT NULL
            )";
        } elseif ($driver === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(255) PRIMARY KEY,
                user_id BIGINT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                payload TEXT NOT NULL,
                last_activity INTEGER NOT NULL
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS sessions (
                id VARCHAR(255) PRIMARY KEY,
                user_id BIGINT UNSIGNED NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                payload LONGTEXT NOT NULL,
                last_activity INT NOT NULL,
                INDEX sessions_user_id_index (user_id),
                INDEX sessions_last_activity_index (last_activity)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        $pdo->exec($sql);
    }

    private function createMigrationsTable(\PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS migrations (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL,
                batch INTEGER NOT NULL
            )";
        } elseif ($driver === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS migrations (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INTEGER NOT NULL
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS migrations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration VARCHAR(255) NOT NULL,
                batch INT NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        $pdo->exec($sql);
    }

    private function createJobsTable(\PDO $pdo, string $driver): void
    {
        if ($driver === 'sqlite') {
            $sql = "CREATE TABLE IF NOT EXISTS jobs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER DEFAULT 0,
                reserved_at INTEGER NULL,
                available_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            )";
        } elseif ($driver === 'pgsql') {
            $sql = "CREATE TABLE IF NOT EXISTS jobs (
                id SERIAL PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload TEXT NOT NULL,
                attempts SMALLINT DEFAULT 0,
                reserved_at INTEGER NULL,
                available_at INTEGER NOT NULL,
                created_at INTEGER NOT NULL
            )";
        } else {
            $sql = "CREATE TABLE IF NOT EXISTS jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                queue VARCHAR(255) NOT NULL,
                payload LONGTEXT NOT NULL,
                attempts TINYINT UNSIGNED DEFAULT 0,
                reserved_at INT UNSIGNED NULL,
                available_at INT UNSIGNED NOT NULL,
                created_at INT UNSIGNED NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        }

        $pdo->exec($sql);
    }

    private function findComposer(): ?string
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $output = [];
            exec('where composer', $output);
            return !empty($output) ? 'composer' : null;
        } else {
            $output = [];
            exec('which composer', $output);
            return !empty($output) ? 'composer' : null;
        }
    }

    private function callComposer(string $composerPath, string $rootPath): void
    {
        $command = "cd " . escapeshellarg($rootPath) . " && {$composerPath} install --no-interaction 2>&1";
        exec($command, $output, $returnVar);

        if ($returnVar !== 0) {
            $this->error("Composer install failed.");
            $this->line(implode("\n", $output));
        } else {
            $this->success("Composer dependencies installed successfully.");
        }
    }
}
