<?php

declare(strict_types=1);

namespace Plugs\Security;

class EmailSecurityValidator
{
    /**
     * Validate an email address for security risks.
     */
    public function validateEmail(array $requestData): array
    {
        $email = $requestData['email'] ?? '';

        if (empty($email)) {
            return ['allowed' => true, 'risk_score' => 0, 'challenge_required' => false];
        }

        // Basic format validation
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->deny('Invalid email format', 0.8);
        }

        // Check disposable email domains
        if ($this->isDisposableEmail($email)) {
            return $this->deny('Disposable email addresses not allowed', 0.85);
        }

        // Check for typos in popular domains
        $typoScore = $this->detectEmailTypos($email);

        return [
            'allowed' => true,
            'risk_score' => min($typoScore, 0.95),
            'challenge_required' => $typoScore > 0.6,
            'typo_detected' => $typoScore > 0.7,
            'suggested_email' => $typoScore > 0.7 ? $this->suggestEmailCorrection($email) : null,
        ];
    }

    private function isDisposableEmail(string $email): bool
    {
        $domain = strtolower(explode('@', $email)[1] ?? '');

        $disposableDomains = [
            'tempmail.com',
            'guerrillamail.com',
            'mailinator.com',
            '10minutemail.com',
            'yopmail.com',
            'throwawaymail.com',
            'temp-mail.org',
            'getnada.com',
            'maildrop.cc',
        ];

        return in_array($domain, $disposableDomains);
    }

    private function detectEmailTypos(string $email): float
    {
        $popularDomains = [
            'gmail.com',
            'yahoo.com',
            'hotmail.com',
            'outlook.com',
            'aol.com',
            'icloud.com',
        ];

        $userDomain = strtolower(explode('@', $email)[1] ?? '');

        foreach ($popularDomains as $correctDomain) {
            $distance = levenshtein($userDomain, $correctDomain);
            if ($distance > 0 && $distance <= 2 && $userDomain !== $correctDomain) {
                return 0.8;
            }
        }

        return 0;
    }

    private function suggestEmailCorrection(string $email): ?string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return null;
        }

        $popularDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com'];
        $userDomain = strtolower($parts[1]);

        foreach ($popularDomains as $correctDomain) {
            if (levenshtein($userDomain, $correctDomain) <= 2) {
                return $parts[0] . '@' . $correctDomain;
            }
        }

        return null;
    }

    private function deny(string $reason, float $riskScore = 1.0): array
    {
        return [
            'allowed' => false,
            'reason' => $reason,
            'risk_score' => $riskScore,
            'challenge_required' => false,
            'timestamp' => time(),
        ];
    }
}
