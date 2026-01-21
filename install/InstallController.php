<?php

declare(strict_types=1);

/**
 * Plugs Framework Installation Controller
 * 
 * Handles all installation logic including:
 * - Server requirements checking
 * - Directory creation
 * - File generation from templates
 * - Database setup
 * - Admin account creation
 */

class InstallController
{
    private array $config;
    private array $sessionData;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->sessionData = $_SESSION['install_data'] ?? [];
    }

    /**
     * Check server requirements
     */
    public function checkRequirements(): array
    {
        $results = [
            'php_version' => [
                'name' => 'PHP Version ' . $this->config['php_version'] . '+',
                'current' => PHP_VERSION,
                'passed' => version_compare(PHP_VERSION, $this->config['php_version'], '>='),
                'required' => true,
            ],
        ];

        // Check required extensions
        foreach ($this->config['extensions'] as $ext => $name) {
            $results['ext_' . $ext] = [
                'name' => $name,
                'current' => extension_loaded($ext) ? 'Installed' : 'Not installed',
                'passed' => extension_loaded($ext),
                'required' => true,
            ];
        }

        // Check optional extensions
        foreach ($this->config['optional_extensions'] as $ext => $name) {
            $results['opt_' . $ext] = [
                'name' => $name . ' (Optional)',
                'current' => extension_loaded($ext) ? 'Installed' : 'Not installed',
                'passed' => extension_loaded($ext),
                'required' => false,
            ];
        }

        // Check directory permissions
        $results['write_root'] = [
            'name' => 'Root directory writable',
            'current' => is_writable(ROOT_PATH) ? 'Writable' : 'Not writable',
            'passed' => is_writable(ROOT_PATH),
            'required' => true,
        ];

        return $results;
    }

    /**
     * Check if all required requirements are met
     */
    public function allRequirementsMet(): bool
    {
        $requirements = $this->checkRequirements();

        foreach ($requirements as $req) {
            if ($req['required'] && !$req['passed']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Process a step submission
     */
    public function processStep(int $step, array $data): array
    {
        switch ($step) {
            case 1:
                return $this->processRequirements($data);
            case 2:
                return $this->processDatabaseConfig($data);
            case 3:
                return $this->processAppSettings($data);
            case 4:
                return $this->processAdminAccount($data);
            case 5:
                return $this->runInstallation();
            default:
                return ['success' => false, 'error' => 'Invalid step'];
        }
    }

    /**
     * Step 1: Validate requirements acceptance
     */
    private function processRequirements(array $data): array
    {
        if (!$this->allRequirementsMet()) {
            return ['success' => false, 'error' => 'Server requirements not met'];
        }

        if (empty($data['accept_license'])) {
            return ['success' => false, 'error' => 'Please accept the license agreement'];
        }

        $_SESSION['install_data']['requirements_accepted'] = true;
        return ['success' => true];
    }

    /**
     * Step 2: Process database configuration
     */
    private function processDatabaseConfig(array $data): array
    {
        $driver = $data['db_driver'] ?? 'mysql';
        $host = $data['db_host'] ?? 'localhost';
        $port = $data['db_port'] ?? ($this->config['database_types'][$driver]['default_port'] ?? 3306);
        $database = $data['db_database'] ?? '';
        $username = $data['db_username'] ?? '';
        $password = $data['db_password'] ?? '';

        // Validate required fields
        if ($driver !== 'sqlite') {
            if (empty($database)) {
                return ['success' => false, 'error' => 'Database name is required'];
            }
            if (empty($username)) {
                return ['success' => false, 'error' => 'Database username is required'];
            }
        }

        // Test connection
        $connectionResult = $this->testDatabaseConnection($driver, $host, $port, $database, $username, $password);

        if (!$connectionResult['success']) {
            return $connectionResult;
        }

        // Save to session
        $_SESSION['install_data']['database'] = [
            'driver' => $driver,
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];

        return ['success' => true];
    }

    /**
     * Test database connection
     */
    private function testDatabaseConnection(
        string $driver,
        string $host,
        int $port,
        string $database,
        string $username,
        string $password
    ): array {
        try {
            if ($driver === 'sqlite') {
                $dsn = 'sqlite:' . ROOT_PATH . 'storage/database.sqlite';
            } elseif ($driver === 'pgsql') {
                $dsn = "pgsql:host={$host};port={$port};dbname={$database}";
            } else {
                $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];

            $pdo = new PDO($dsn, $username, $password, $options);

            return ['success' => true];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Step 3: Process application settings
     */
    private function processAppSettings(array $data): array
    {
        $appName = trim($data['app_name'] ?? '');
        $appUrl = trim($data['app_url'] ?? '');
        $appEnv = $data['app_env'] ?? 'local';
        $appTimezone = $data['app_timezone'] ?? 'UTC';

        if (empty($appName)) {
            return ['success' => false, 'error' => 'Application name is required'];
        }

        if (empty($appUrl)) {
            return ['success' => false, 'error' => 'Application URL is required'];
        }

        // Generate app key
        $appKey = $this->generateAppKey();

        $_SESSION['install_data']['app'] = [
            'name' => $appName,
            'url' => rtrim($appUrl, '/'),
            'env' => $appEnv,
            'timezone' => $appTimezone,
            'key' => $appKey,
        ];

        return ['success' => true];
    }

    /**
     * Step 4: Process admin account
     */
    private function processAdminAccount(array $data): array
    {
        $name = trim($data['admin_name'] ?? '');
        $email = trim($data['admin_email'] ?? '');
        $password = $data['admin_password'] ?? '';
        $passwordConfirm = $data['admin_password_confirm'] ?? '';

        if (empty($name)) {
            return ['success' => false, 'error' => 'Admin name is required'];
        }

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'error' => 'Valid email address is required'];
        }

        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters'];
        }

        if ($password !== $passwordConfirm) {
            return ['success' => false, 'error' => 'Passwords do not match'];
        }

        $_SESSION['install_data']['admin'] = [
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_ARGON2ID),
        ];

        return ['success' => true];
    }

    /**
     * Step 5: Run the actual installation
     */
    private function runInstallation(): array
    {
        $installData = $_SESSION['install_data'] ?? [];

        try {
            // 1. Create directories
            $this->createDirectories();

            // 2. Generate files from templates
            $this->generateFiles($installData);

            // 3. Create database tables
            $this->createDatabaseTables($installData);

            // 4. Create admin user
            $this->createAdminUser($installData);

            // 5. Create .installed marker
            file_put_contents(ROOT_PATH . '.installed', date('Y-m-d H:i:s'));

            // 6. Clear session
            unset($_SESSION['install_data']);

            return ['success' => true];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Create all required directories
     */
    private function createDirectories(): void
    {
        foreach ($this->config['directories'] as $dir) {
            $path = ROOT_PATH . $dir;
            if (!is_dir($path)) {
                if (!mkdir($path, 0755, true)) {
                    throw new Exception("Failed to create directory: {$dir}");
                }
            }
        }

        // Create .gitkeep files in empty directories
        $gitkeepDirs = ['storage/logs', 'storage/views', 'storage/framework', 'database/migrations'];
        foreach ($gitkeepDirs as $dir) {
            $path = ROOT_PATH . $dir . '/.gitkeep';
            if (!file_exists($path)) {
                file_put_contents($path, '');
            }
        }
    }

    /**
     * Generate files from templates
     */
    private function generateFiles(array $installData): void
    {
        foreach ($this->config['files'] as $targetFile => $templateFile) {
            $templatePath = TEMPLATES_PATH . $templateFile;
            $targetPath = ROOT_PATH . $targetFile;

            if (!file_exists($templatePath)) {
                throw new Exception("Template not found: {$templateFile}");
            }

            $content = file_get_contents($templatePath);
            $content = $this->replacePlaceholders($content, $installData);

            // Create parent directory if needed
            $parentDir = dirname($targetPath);
            if (!is_dir($parentDir)) {
                mkdir($parentDir, 0755, true);
            }

            if (file_put_contents($targetPath, $content) === false) {
                throw new Exception("Failed to create file: {$targetFile}");
            }
        }
    }

    /**
     * Replace placeholders in template content
     */
    private function replacePlaceholders(string $content, array $installData): string
    {
        $replacements = [
            // App settings
            '{{APP_NAME}}' => $installData['app']['name'] ?? 'Plugs Framework',
            '{{APP_URL}}' => $installData['app']['url'] ?? 'http://localhost',
            '{{APP_ENV}}' => $installData['app']['env'] ?? 'local',
            '{{APP_TIMEZONE}}' => $installData['app']['timezone'] ?? 'UTC',
            '{{APP_KEY}}' => $installData['app']['key'] ?? $this->generateAppKey(),
            '{{APP_DEBUG}}' => ($installData['app']['env'] ?? 'local') === 'local' ? 'true' : 'false',

            // Database settings
            '{{DB_DRIVER}}' => $installData['database']['driver'] ?? 'mysql',
            '{{DB_HOST}}' => $installData['database']['host'] ?? 'localhost',
            '{{DB_PORT}}' => (string) ($installData['database']['port'] ?? 3306),
            '{{DB_DATABASE}}' => $installData['database']['database'] ?? 'plugs',
            '{{DB_USERNAME}}' => $installData['database']['username'] ?? 'root',
            '{{DB_PASSWORD}}' => $installData['database']['password'] ?? '',

            // Generated values
            '{{GENERATED_DATE}}' => date('Y-m-d H:i:s'),
            '{{YEAR}}' => date('Y'),
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Create database tables
     */
    private function createDatabaseTables(array $installData): void
    {
        $db = $installData['database'];

        try {
            if ($db['driver'] === 'sqlite') {
                $dsn = 'sqlite:' . ROOT_PATH . 'storage/database.sqlite';
                $pdo = new PDO($dsn);
            } elseif ($db['driver'] === 'pgsql') {
                $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname={$db['database']}";
                $pdo = new PDO($dsn, $db['username'], $db['password']);
            } else {
                $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['username'], $db['password']);
            }

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create users table
            $this->createUsersTable($pdo, $db['driver']);

            // Create sessions table
            $this->createSessionsTable($pdo, $db['driver']);

            // Create migrations table
            $this->createMigrationsTable($pdo, $db['driver']);

        } catch (PDOException $e) {
            throw new Exception('Database setup failed: ' . $e->getMessage());
        }
    }

    /**
     * Create users table
     */
    private function createUsersTable(PDO $pdo, string $driver): void
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

    /**
     * Create sessions table
     */
    private function createSessionsTable(PDO $pdo, string $driver): void
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

    /**
     * Create migrations table
     */
    private function createMigrationsTable(PDO $pdo, string $driver): void
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

    /**
     * Create admin user
     */
    private function createAdminUser(array $installData): void
    {
        $admin = $installData['admin'];
        $db = $installData['database'];

        try {
            if ($db['driver'] === 'sqlite') {
                $dsn = 'sqlite:' . ROOT_PATH . 'storage/database.sqlite';
                $pdo = new PDO($dsn);
            } elseif ($db['driver'] === 'pgsql') {
                $dsn = "pgsql:host={$db['host']};port={$db['port']};dbname={$db['database']}";
                $pdo = new PDO($dsn, $db['username'], $db['password']);
            } else {
                $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset=utf8mb4";
                $pdo = new PDO($dsn, $db['username'], $db['password']);
            }

            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role, created_at) VALUES (?, ?, ?, 'admin', ?)");
            $stmt->execute([
                $admin['name'],
                $admin['email'],
                $admin['password'],
                date('Y-m-d H:i:s'),
            ]);

        } catch (PDOException $e) {
            throw new Exception('Failed to create admin user: ' . $e->getMessage());
        }
    }

    /**
     * Generate a random application key
     */
    private function generateAppKey(): string
    {
        return 'base64:' . base64_encode(random_bytes(32));
    }

    /**
     * Get data for a specific step
     */
    public function getStepData(int $step): array
    {
        return [
            'step' => $step,
            'total_steps' => 5,
            'session_data' => $_SESSION['install_data'] ?? [],
            'config' => $this->config,
        ];
    }
}
