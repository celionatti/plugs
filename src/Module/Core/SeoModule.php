<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class SeoModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Seo';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('seo', function () {
            return new \Plugs\Support\SEO(config('seo'));
        });
    }

    public function boot(Plugs $app): void
    {
        $config = config('seo', []);

        // 1. Dynamic Sitemap
        if ($config['sitemap']['enabled'] ?? false) {
            $path = $config['sitemap']['path'] ?? '/sitemap.xml';
            \Plugs\Facades\Route::get($path, function () {
                $sitemap = \Plugs\Support\Sitemap::create();
                
                // Allow models to hook into this via events or hooks in the future
                // For now, it's a foundation that can be extended
                
                return response($sitemap->render())->withHeader('Content-Type', 'application/xml');
            });
        }

        // 2. Dynamic Robots.txt
        if ($config['robots_txt']['enabled'] ?? false) {
            $path = $config['robots_txt']['path'] ?? '/robots.txt';
            \Plugs\Facades\Route::get($path, function () {
                $robots = \Plugs\Support\Robots::create()
                    ->userAgent('*')
                    ->allow('/');
                
                if (config('seo.sitemap.enabled')) {
                    $robots->sitemap(url(config('seo.sitemap.path', '/sitemap.xml')));
                }

                return response($robots->render())->withHeader('Content-Type', 'text/plain');
            });
        }
    }
}
