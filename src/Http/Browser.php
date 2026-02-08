<?php

declare(strict_types=1);

namespace Plugs\Http;

/*
|--------------------------------------------------------------------------
| Browser Class
|--------------------------------------------------------------------------
|
| This class provides advanced functionality for getting client IP address
| and parsing User Agent strings to detect browser, OS, and device type.
*/

/**
 * @phpstan-consistent-constructor
 */
class Browser
{
    /** @var string|null */
    protected $ip;

    /** @var string|null */
    protected $userAgent;

    /** @var array */
    protected $server;

    /**
     * Create a new Browser instance.
     *
     * @param array|null $server
     */
    public function __construct(?array $server = null)
    {
        $this->server = $server ?? $_SERVER;
        $this->userAgent = $this->server['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Create a new instance statically.
     *
     * @param array|null $server
     * @return static
     */
    public static function make(?array $server = null): self
    {
        return new static($server);
    }

    /**
     * Get the client IP address.
     *
     * @return string
     */
    public function ip(): string
    {
        if ($this->ip) {
            return $this->ip;
        }

        $headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_REAL_IP',       // Nginx
        ];

        foreach ($headers as $header) {
            if (isset($this->server[$header]) && !empty($this->server[$header])) {
                foreach (explode(',', $this->server[$header]) as $ip) {
                    $ip = trim($ip);
                    if ($this->isValidIp($ip)) {
                        return $this->ip = $ip;
                    }
                }
            }
        }

        return $this->ip = '0.0.0.0';
    }

    /**
     * Validate IP address.
     *
     * @param string $ip
     * @return bool
     */
    protected function isValidIp(string $ip): bool
    {
        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    }

    /**
     * Get the raw user agent string.
     *
     * @return string
     */
    public function userAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Detect the browser name.
     *
     * @return string
     */
    public function browser(): string
    {
        $browser = 'Unknown Browser';
        $agent = $this->userAgent;

        $browserArray = [
            '/msie/i' => 'Internet Explorer',
            '/firefox/i' => 'Firefox',
            '/safari/i' => 'Safari',
            '/chrome/i' => 'Chrome',
            '/edge/i' => 'Edge',
            '/opera/i' => 'Opera',
            '/netscape/i' => 'Netscape',
            '/maxthon/i' => 'Maxthon',
            '/konqueror/i' => 'Konqueror',
            '/mobile/i' => 'Handheld Browser',
        ];

        foreach ($browserArray as $regex => $value) {
            if (preg_match($regex, $agent)) {
                $browser = $value;
            }
        }

        return $browser;
    }

    /**
     * Detect the operating system.
     *
     * @return string
     */
    public function os(): string
    {
        $os = 'Unknown OS';
        $agent = $this->userAgent;

        $osArray = [
            '/windows nt 10/i' => 'Windows 10',
            '/windows nt 6.3/i' => 'Windows 8.1',
            '/windows nt 6.2/i' => 'Windows 8',
            '/windows nt 6.1/i' => 'Windows 7',
            '/windows nt 6.0/i' => 'Windows Vista',
            '/windows nt 5.2/i' => 'Windows Server 2003/XP x64',
            '/windows nt 5.1/i' => 'Windows XP',
            '/windows xp/i' => 'Windows XP',
            '/windows nt 5.0/i' => 'Windows 2000',
            '/windows me/i' => 'Windows ME',
            '/win98/i' => 'Windows 98',
            '/win95/i' => 'Windows 95',
            '/win16/i' => 'Windows 3.11',
            '/macintosh|mac os x/i' => 'Mac OS X',
            '/mac_powerpc/i' => 'Mac OS 9',
            '/linux/i' => 'Linux',
            '/ubuntu/i' => 'Ubuntu',
            '/iphone/i' => 'iPhone',
            '/ipod/i' => 'iPod',
            '/ipad/i' => 'iPad',
            '/android/i' => 'Android',
            '/blackberry/i' => 'BlackBerry',
            '/webos/i' => 'Mobile',
        ];

        foreach ($osArray as $regex => $value) {
            if (preg_match($regex, $agent)) {
                $os = $value;
            }
        }

        return $os;
    }

    /**
     * Detect the device platform.
     *
     * @return string
     */
    public function platform(): string
    {
        $platform = 'Unknown Platform';
        $agent = $this->userAgent;

        $platforms = [
            'windows' => 'Windows',
            'iPad' => 'iPad',
            'iPhone' => 'iPhone',
            'mac' => 'Apple',
            'android' => 'Android',
            'linux' => 'Linux',
        ];

        foreach ($platforms as $key => $value) {
            if (stripos($agent, (string) $key) !== false) {
                $platform = $value;

                break;
            }
        }

        return $platform;
    }

    /**
     * Check if the client is on a mobile device.
     *
     * @return bool
     */
    public function isMobile(): bool
    {
        return (bool) preg_match("/(android|avantgo|blackberry|bolt|boost|cricket|docomo|fone|hiptop|mini|mobi|palm|phone|pie|tablet|up\.browser|up\.link|webos|wos)/i", $this->userAgent);
    }

    /**
     * Check if the client is on a tablet device.
     *
     * @return bool
     */
    public function isTablet(): bool
    {
        return (bool) preg_match("/(ipad|tablet|(android(?!.*mobile))|(windows(?!.*phone)(.*touch))|kindle|playbook|silk|puffin)/i", $this->userAgent);
    }

    /**
     * Check if the client is on a desktop.
     *
     * @return bool
     */
    public function isDesktop(): bool
    {
        return !$this->isMobile() && !$this->isTablet();
    }

    /**
     * Check if the user agent is a bot or crawler.
     *
     * @return bool
     */
    public function isRobot(): bool
    {
        return (bool) preg_match("/(googlebot|bingbot|slurp|duckduckbot|baiduspider|yandexbot|sogou|exabot|facebookexternalhit|ia_archiver)/i", $this->userAgent);
    }

    /**
     * Get all info as an array.
     *
     * @return array
     */
    public function all(): array
    {
        return [
            'ip' => $this->ip(),
            'user_agent' => $this->userAgent(),
            'browser' => $this->browser(),
            'os' => $this->os(),
            'platform' => $this->platform(),
            'is_mobile' => $this->isMobile(),
            'is_tablet' => $this->isTablet(),
            'is_desktop' => $this->isDesktop(),
            'is_robot' => $this->isRobot(),
        ];
    }
}
