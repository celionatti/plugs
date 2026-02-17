<?php

declare(strict_types=1);

namespace Plugs\Security\Scanner\Scanners;

use Plugs\Security\Scanner\BaseScanner;

class CsrfScanner extends BaseScanner
{
    public function getName(): string
    {
        return 'CSRF Security Scanner';
    }

    public function scan(string $path, string $content): array
    {
        $issues = [];

        // Find all <form> tags with method="POST"
        if (preg_match_all('/<form[^>]*method=["\']post["\'][^>]*>(.*?)<\/form>/is', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $index => $match) {
                $formContent = $matches[1][$index][0];

                // Check if @csrf, <csrf />, or name="_token" is present
                if (!str_contains($formContent, '@csrf') && !str_contains($formContent, '<csrf />') && !preg_match('/name=["\']_token["\']/i', $formContent)) {
                    $issues[] = $this->createIssue(
                        $path,
                        $this->getLineNumber($content, $match[1]),
                        'POST form missing CSRF protection.',
                        'CRITICAL',
                        'Add the @csrf directive inside your form.'
                    );
                }
            }
        }

        return $issues;
    }
}
