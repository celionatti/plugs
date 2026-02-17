<?php

declare(strict_types=1);

namespace Plugs\Security\Scanner\Scanners;

use Plugs\Security\Scanner\BaseScanner;

class WeakPasswordScanner extends BaseScanner
{
    public function getName(): string
    {
        return 'Password Security Scanner';
    }

    public function scan(string $path, string $content): array
    {
        $issues = [];

        // 1. Check for legacy hashing functions
        $unsafeHashing = ['md5\(', 'sha1\(', 'crypt\('];
        foreach ($unsafeHashing as $func) {
            if (preg_match_all('/' . $func . '/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $issues[] = $this->createIssue(
                        $path,
                        $this->getLineNumber($content, $match[1]),
                        "Insecure hashing function detected: {$match[0]}",
                        'CRITICAL',
                        'Use password_hash() with PASSWORD_DEFAULT, PASSWORD_ARGON2I, or PASSWORD_ARGON2ID instead.'
                    );
                }
            }
        }

        // 2. Check for weak password validation rules in controllers
        if (str_contains($content, "'password'") && (str_contains($content, 'validate') || str_contains($content, 'rules'))) {
            // Check if it's a validation array/string and if min:8 (or more) is missing
            $hasMinEight = preg_match('/min:[8-9]|min:[1-9][0-9]/', $content);
            $hasArgon = preg_match('/argon2/i', $content);

            if (!$hasMinEight && !$hasArgon) {
                $issues[] = $this->createIssue(
                    $path,
                    1,
                    'Potential weak password validation or missing minimum length requirement.',
                    'MEDIUM',
                    "Ensure password validation includes 'min:8' or is using strong hashing like Argon2."
                );
            }
        }

        return $issues;
    }
}
