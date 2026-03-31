<?php

declare(strict_types=1);

namespace Plugs\Support;

/**
 * Class Sitemap
 * 
 * A simple utility for generating Google-compatible sitemap.xml files.
 */
class Sitemap
{
    protected array $urls = [];

    /**
     * Add a URL to the sitemap.
     * 
     * @param string $url The full URL
     * @param string|null $lastMod Last modification date (Y-m-d)
     * @param string $changeFreq 'always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'
     * @param float $priority Priority from 0.0 to 1.0
     * @return self
     */
    public function add(string $url, ?string $lastMod = null, string $changeFreq = 'daily', float $priority = 0.5): self
    {
        $this->urls[] = [
            'loc' => $url,
            'lastmod' => $lastMod ?? date('Y-m-d'),
            'changefreq' => $changeFreq,
            'priority' => number_format($priority, 1, '.', ''),
        ];
        return $this;
    }

    /**
     * Render the sitemap as XML.
     * 
     * @return string
     */
    public function render(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;

        foreach ($this->urls as $url) {
            $xml .= '    <url>' . PHP_EOL;
            $xml .= "        <loc>{$url['loc']}</loc>" . PHP_EOL;
            $xml .= "        <lastmod>{$url['lastmod']}</lastmod>" . PHP_EOL;
            $xml .= "        <changefreq>{$url['changefreq']}</changefreq>" . PHP_EOL;
            $xml .= "        <priority>{$url['priority']}</priority>" . PHP_EOL;
            $xml .= '    </url>' . PHP_EOL;
        }

        $xml .= '</urlset>';

        return $xml;
    }

    /**
     * Helper to create a new instance.
     */
    public static function create(): self
    {
        return new static();
    }
}
