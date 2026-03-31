<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Facades\SEO;
use Plugs\Utils\Seo as SeoUtils;

/**
 * Trait HasSeo
 *
 * This trait provides a bridge between Eloquent models and the SEO support system.
 */
trait HasSeo
{
    /**
     * Define the SEO mapping for the model.
     * 
     * Supported keys: 'title', 'description', 'image', 'type', 'url'
     *
     * @return array
     */
    public function seoMap(): array
    {
        return [
            'title' => 'title',
            'description' => 'content',
        ];
    }

    /**
     * Get the SEO metadata for this model instance.
     * 
     * @param array $overrides Manual overrides for metadata
     * @return \Plugs\Support\SEO
     */
    public function toSeo(array $overrides = []): \Plugs\Support\SEO
    {
        $map = $this->seoMap();
        $seo = SEO::getFacadeRoot() ?? new \Plugs\Support\SEO(config('seo', []));

        // 1. Resolve Title
        $titleRaw = $overrides['title'] ?? $this->{$map['title'] ?? 'title'} ?? null;
        if ($titleRaw) {
            $seo->setTitle(SeoUtils::generateTitle((string)$titleRaw));
        }

        // 2. Resolve Description
        $descRaw = $overrides['description'] ?? $this->{$map['description'] ?? 'content'} ?? $this->{$map['description'] ?? 'description'} ?? null;
        if ($descRaw) {
            $seo->setDescription(SeoUtils::generateDescription((string)$descRaw));
        }

        // 3. Resolve Image
        $imageRaw = $overrides['image'] ?? $this->{$map['image'] ?? 'image'} ?? $this->{$map['image'] ?? 'cover_image'} ?? null;
        if ($imageRaw) {
            $seo->setImage((string)$imageRaw);
        }

        // 4. Resolve URL
        $urlRaw = $overrides['url'] ?? $this->{$map['url'] ?? 'url'} ?? (method_exists($this, 'url') ? $this->url() : null);
        if ($urlRaw) {
            $seo->setUrl((string)$urlRaw);
        }

        return $seo;
    }
}
