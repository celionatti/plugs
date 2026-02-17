<?php

declare(strict_types=1);

namespace Plugs\Security\Scanner\Scanners;

use Plugs\Security\Scanner\BaseScanner;

class RawSqlScanner extends BaseScanner
{
    public function getName(): string
    {
        return 'Raw SQL Detector';
    }

    public function scan(string $path, string $content): array
    {
        $issues = [];

        // Patterns for raw SQL usage
        $patterns = [
            '/DB::raw\(/i' => 'Direct use of DB::raw() can lead to SQL injection if not handled carefully.',
            '/->whereRaw\(/i' => 'Use of ->whereRaw() is discouraged. Prefer prepared statements or fluent builder.',
            '/->selectRaw\(/i' => 'Use of ->selectRaw() can be risky.',
            '/->havingRaw\(/i' => 'Use of ->havingRaw() detected.',
            '/->orderByRaw\(/i' => 'Use of ->orderByRaw() detected.',
            '/\$connection->query\(\$sql/i' => 'Executing raw SQL variables via connection->query() is dangerous.',
        ];

        foreach ($patterns as $pattern => $message) {
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $issues[] = $this->createIssue(
                        $path,
                        $this->getLineNumber($content, $match[1]),
                        $message,
                        'HIGH',
                        'Use parameterized queries or the fluent query builder instead.'
                    );
                }
            }
        }

        return $issues;
    }
}
