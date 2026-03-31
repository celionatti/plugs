<?php

declare(strict_types=1);

namespace Plugs\Support;

/**
 * Class Robots
 * 
 * A simple utility for generating robots.txt files.
 */
class Robots
{
    protected array $rules = [];
    protected array $sitemaps = [];
    protected string $userAgent = '*';

    /**
     * Set the user agent for the following rules.
     */
    public function userAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Add an allow rule.
     */
    public function allow(string|array $path): self
    {
        $paths = (array) $path;
        foreach ($paths as $p) {
            $this->rules[$this->userAgent]['allow'][] = $p;
        }
        return $this;
    }

    /**
     * Add a disallow rule.
     */
    public function disallow(string|array $path): self
    {
        $paths = (array) $path;
        foreach ($paths as $p) {
            $this->rules[$this->userAgent]['disallow'][] = $p;
        }
        return $this;
    }

    /**
     * Add a sitemap to the robots.txt.
     */
    public function sitemap(string $url): self
    {
        $this->sitemaps[] = $url;
        return $this;
    }

    /**
     * Render the robots.txt content.
     */
    public function render(): string
    {
        $lines = [];

        foreach ($this->rules as $userAgent => $directives) {
            $lines[] = "User-agent: {$userAgent}";

            if (isset($directives['disallow'])) {
                foreach ($directives['disallow'] as $path) {
                    $lines[] = "Disallow: {$path}";
                }
            }

            if (isset($directives['allow'])) {
                foreach ($directives['allow'] as $path) {
                    $lines[] = "Allow: {$path}";
                }
            }

            $lines[] = ""; // Empty line between user agents
        }

        foreach ($this->sitemaps as $sitemap) {
            $lines[] = "Sitemap: {$sitemap}";
        }

        return implode(PHP_EOL, $lines);
    }

    /**
     * Helper to create a new instance.
     */
    public static function create(): self
    {
        return new static();
    }
}
