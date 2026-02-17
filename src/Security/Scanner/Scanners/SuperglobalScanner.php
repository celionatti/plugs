<?php

declare(strict_types=1);

namespace Plugs\Security\Scanner\Scanners;

use Plugs\Security\Scanner\BaseScanner;

class SuperglobalScanner extends BaseScanner
{
    public function getName(): string
    {
        return 'Superglobal usage Detector';
    }

    public function scan(string $path, string $content): array
    {
        $issues = [];

        // Exclude the Request class itself from this check
        if (str_contains($path, 'src/Http/Message/ServerRequest.php')) {
            return [];
        }

        $superglobals = ['\$_GET', '\$_POST', '\$_REQUEST', '\$_COOKIE', '\$_SERVER', '\$_ENV'];

        foreach ($superglobals as $sg) {
            $pattern = '/(?<![\'"])(' . $sg . ')/';
            if (preg_match_all($pattern, $content, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as $match) {
                    $issues[] = $this->createIssue(
                        $path,
                        $this->getLineNumber($content, $match[1]),
                        "Direct access to {$match[0]} detected.",
                        'MEDIUM',
                        'Use the Plugs Request object instead: $request->get(), $request->post(), etc.'
                    );
                }
            }
        }

        return $issues;
    }
}
