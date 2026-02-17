<?php

declare(strict_types=1);

namespace Plugs\Security\Scanner;

abstract class BaseScanner
{
    /**
     * Scan the given file content for security issues.
     *
     * @param string $path
     * @param string $content
     * @return array Array of issues found
     */
    abstract public function scan(string $path, string $content): array;

    /**
     * Get the name of the scanner.
     */
    abstract public function getName(): string;

    /**
     * Create a standardized issue report.
     */
    protected function createIssue(string $path, int $line, string $message, string $severity = 'MEDIUM', string $suggestion = ''): array
    {
        return [
            'scanner' => $this->getName(),
            'path' => $path,
            'line' => $line,
            'message' => $message,
            'severity' => $severity,
            'suggestion' => $suggestion,
        ];
    }

    /**
     * Helper to find line number of a match.
     */
    protected function getLineNumber(string $content, int $offset): int
    {
        return substr_count($content, "\n", 0, $offset) + 1;
    }
}
