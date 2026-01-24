<?php

declare(strict_types=1);

namespace Plugs\Support;

use Exception;
use Plugs\Database\Connection;

class HealthCheck
{
    /**
     * Run all health checks.
     *
     * @return array
     */
    public function runAll(): array
    {
        return [
            'php' => $this->checkPHPVersion(),
            'extensions' => $this->checkExtensions(),
            'directories' => $this->checkDirectoryPermissions(),
            'environment' => $this->checkEnvironmentFile(),
            'app_key' => $this->checkAppKey(),
            'database' => $this->checkDatabaseConnection(),
        ];
    }

    /**
     * Check PHP version.
     */
    public function checkPHPVersion(): array
    {
        $minVersion = '8.0.0';
        $currentVersion = PHP_VERSION;
        $status = version_compare($currentVersion, $minVersion, '>=');

        return [
            'name' => 'PHP Version',
            'status' => $status,
            'message' => $status ? "Version {$currentVersion} is compatible." : "PHP >= {$minVersion} is required (Current: {$currentVersion}).",
            'required' => true,
        ];
    }

    /**
     * Check required extensions.
     */
    public function checkExtensions(): array
    {
        $required = ['pdo', 'mbstring', 'openssl', 'json', 'curl', 'fileinfo'];
        $missing = [];

        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        $status = empty($missing);

        return [
            'name' => 'PHP Extensions',
            'status' => $status,
            'message' => $status ? "All required extensions are loaded." : "Missing extensions: " . implode(', ', $missing),
            'required' => true,
        ];
    }

    /**
     * Check directory permissions.
     */
    public function checkDirectoryPermissions(): array
    {
        $directories = [
            'storage',
            'storage/logs',
            'storage/framework',
            'storage/views',
            'storage/cache',
            'bootstrap/cache',
        ];

        $unwritable = [];

        foreach ($directories as $dir) {
            $path = base_path($dir);
            if (!is_dir($path) || !is_writable($path)) {
                $unwritable[] = $dir;
            }
        }

        $status = empty($unwritable);

        return [
            'name' => 'Directory Permissions',
            'status' => $status,
            'message' => $status ? "All required directories are writable." : "Directories not writable: " . implode(', ', $unwritable),
            'required' => true,
        ];
    }

    /**
     * Check if environment file exists.
     */
    public function checkEnvironmentFile(): array
    {
        $status = file_exists(base_path('.env'));

        return [
            'name' => 'Environment File',
            'status' => $status,
            'message' => $status ? ".env file exists." : ".env file is missing. Please copy .env.example to .env.",
            'required' => true,
        ];
    }

    /**
     * Check if APP_KEY is set.
     */
    public function checkAppKey(): array
    {
        $key = env('APP_KEY');
        $status = !empty($key) && $key !== 'base64:';

        return [
            'name' => 'Application Key',
            'status' => $status,
            'message' => $status ? "APP_KEY is set." : "APP_KEY is not set. Run 'php theplugs key:generate'.",
            'required' => true,
        ];
    }

    /**
     * Check database connection.
     */
    public function checkDatabaseConnection(): array
    {
        try {
            $connection = Connection::getInstance()->getPDO();
            $status = true;
            $message = "Database connection successful.";
        } catch (Exception $e) {
            $status = false;
            $message = "Database connection failed: " . $e->getMessage();
        }

        return [
            'name' => 'Database Connection',
            'status' => $status,
            'message' => $message,
            'required' => false,
        ];
    }
}
