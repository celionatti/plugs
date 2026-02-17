<?php

declare(strict_types=1);

namespace Plugs\Security\Scanner\Scanners;

use Plugs\Security\Scanner\BaseScanner;

class UploadScanner extends BaseScanner
{
    public function getName(): string
    {
        return 'File Upload Security Scanner';
    }

    public function scan(string $path, string $content): array
    {
        $issues = [];

        // Detect move_uploaded_file or Plugs store() without extension validation
        if (preg_match_all('/(move_uploaded_file|store|moveTo)\(/i', $content, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                // Check if subsequent lines have MIME type or extension check (simple heuristic)
                if (!preg_match('/(getMimeType|getClientOriginalExtension|in_array|allowed)/i', $content)) {
                    $issues[] = $this->createIssue(
                        $path,
                        $this->getLineNumber($content, $match[1]),
                        'File upload detected without apparent extension validation.',
                        'HIGH',
                        'Always validate file extensions and MIME types before storing uploads.'
                    );
                }
            }
        }

        return $issues;
    }
}
