<?php

declare(strict_types=1);

namespace Plugs\Security\Scanner;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class SecurityScanner
{
    protected array $scanners = [];
    protected array $excludedPaths = ['vendor', 'storage', 'tests', '.git'];

    public function __construct()
    {
        $this->registerDefaultScanners();
    }

    protected function registerDefaultScanners(): void
    {
        $this->scanners[] = new \Plugs\Security\Scanner\Scanners\RawSqlScanner();
        $this->scanners[] = new \Plugs\Security\Scanner\Scanners\SuperglobalScanner();
        $this->scanners[] = new \Plugs\Security\Scanner\Scanners\CsrfScanner();
        $this->scanners[] = new \Plugs\Security\Scanner\Scanners\WeakPasswordScanner();
        $this->scanners[] = new \Plugs\Security\Scanner\Scanners\UploadScanner();
    }

    /**
     * Scan the project for security issues.
     */
    public function scanProject(string $directory): array
    {
        $issues = [];
        $filesScanned = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isDir() || $file->getExtension() !== 'php' && $file->getExtension() !== 'html') {
                continue;
            }

            $path = $file->getPathname();

            // Skip excluded paths
            foreach ($this->excludedPaths as $excluded) {
                if (str_contains($path, DIRECTORY_SEPARATOR . $excluded . DIRECTORY_SEPARATOR)) {
                    continue 2;
                }
            }

            $filesScanned++;
            $content = file_get_contents($path);

            foreach ($this->scanners as $scanner) {
                $found = $scanner->scan($path, $content);
                if (!empty($found)) {
                    $issues = array_merge($issues, $found);
                }
            }
        }

        return [
            'issues' => $issues,
            'files_scanned' => $filesScanned,
            'timestamp' => date('Y-m-d H:i:s'),
        ];
    }
}
