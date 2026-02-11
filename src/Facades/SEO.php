<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static \Plugs\Support\SEO setTitle(string $title, bool $withAppend = true)
 * @method static \Plugs\Support\SEO setDescription(string $description)
 * @method static \Plugs\Support\SEO setImage(string $image)
 * @method static \Plugs\Support\SEO setUrl(string $url)
 * @method static \Plugs\Support\SEO addMetadata(string $name, string $content, string $type = 'name')
 * @method static \Plugs\Support\SEO addJsonLd(array $data)
 * @method static string render()
 * 
 * @see \Plugs\Support\SEO
 */
class SEO extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'seo';
    }
}
