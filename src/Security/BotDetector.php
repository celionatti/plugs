<?php

declare(strict_types=1);

namespace Plugs\Security;

class BotDetector
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Detect if the request is likely from a bot.
     */
    public function detectBots(array $requestData): array
    {
        $userAgent = strtolower($requestData['user_agent'] ?? '');
        $headers = $requestData['headers'] ?? [];

        $botIndicators = 0;
        $maxIndicators = 10;
        $details = [];

        $suspiciousHeaders = $this->config['bot_detection']['suspicious_headers'] ?? [];
        $blockSuspicious = $this->config['bot_detection']['block_suspicious_bots'] ?? false;

        // Check for known bot user agents
        foreach ($suspiciousHeaders as $botKeyword) {
            if (strpos($userAgent, $botKeyword) !== false) {
                $botIndicators += 2;
                $details[] = "bot_keyword:{$botKeyword}";
            }
        }

        // Check for empty or suspicious user agent
        if (empty($userAgent) || strlen($userAgent) < 10) {
            $botIndicators += 1.5;
            $details[] = 'suspicious_ua_length';
        }

        // Check for missing standard headers
        $requiredHeaders = ['accept', 'accept-language'];
        foreach ($requiredHeaders as $header) {
            if (!isset($headers[$header])) {
                $botIndicators += 0.5;
                $details[] = "missing_header:{$header}";
            }
        }

        // Check User-Agent consistency
        if (!$this->isUserAgentConsistent($userAgent, $headers)) {
            $botIndicators += 1;
            $details[] = 'inconsistent_ua';
        }

        // Check for headless browser signatures
        if ($this->isHeadlessBrowser($userAgent)) {
            $botIndicators += 1.5;
            $details[] = 'headless_browser';
        }

        $botScore = min($botIndicators / $maxIndicators, 1.0);

        // Block high-confidence bots
        if ($botScore > 0.75 && $blockSuspicious) {
            return $this->deny('Automated bot detected', $botScore);
        }

        return [
            'allowed' => true,
            'risk_score' => $botScore * 0.8,
            'challenge_required' => $botScore > 0.4,
            'challenge_type' => $botScore > 0.6 ? 'advanced_captcha' : 'captcha',
            'details' => $details,
        ];
    }

    private function isUserAgentConsistent(string $userAgent, array $headers): bool
    {
        $isBrowser = preg_match('/(mozilla|chrome|safari|firefox|edge)/i', $userAgent);
        $hasAccept = isset($headers['accept']);

        return $isBrowser === $hasAccept;
    }

    private function isHeadlessBrowser(string $userAgent): bool
    {
        $headlessSignatures = ['headless', 'phantom', 'selenium', 'puppeteer', 'playwright'];

        foreach ($headlessSignatures as $signature) {
            if (strpos($userAgent, $signature) !== false) {
                return true;
            }
        }

        return false;
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
