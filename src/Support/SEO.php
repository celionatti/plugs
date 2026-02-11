<?php

declare(strict_types=1);

namespace Plugs\Support;

class SEO
{
    protected array $config;
    protected array $tags = [];
    protected array $jsonLd = [];
    protected ?string $title = null;
    protected ?string $description = null;
    protected ?string $image = null;
    protected ?string $url = null;
    protected array $metadata = [];

    public function __construct(array $config = [])
    {
        $this->config = $config;
        $this->title = $config['default_title'] ?? null;
        $this->description = $config['default_description'] ?? null;
        $this->image = $config['default_image'] ?? null;
    }

    public function setTitle(string $title, bool $withAppend = true): self
    {
        $appendix = $withAppend ? ($this->config['title_appendix'] ?? '') : '';
        $this->title = $title . $appendix;
        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setImage(string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;
        return $this;
    }

    public function addMetadata(string $name, string $content, string $type = 'name'): self
    {
        $this->metadata[] = ['type' => $type, 'name' => $name, 'content' => $content];
        return $this;
    }

    public function addJsonLd(array $data): self
    {
        $this->jsonLd[] = $data;
        return $this;
    }

    public function render(): string
    {
        $html = [];

        if ($this->title) {
            $html[] = "<title>{$this->title}</title>";
            $html[] = "<meta name=\"title\" content=\"{$this->title}\">";
            $html[] = "<meta property=\"og:title\" content=\"{$this->title}\">";
            $html[] = "<meta name=\"twitter:title\" content=\"{$this->title}\">";
        }

        if ($this->description) {
            $html[] = "<meta name=\"description\" content=\"{$this->description}\">";
            $html[] = "<meta property=\"og:description\" content=\"{$this->description}\">";
            $html[] = "<meta name=\"twitter:description\" content=\"{$this->description}\">";
        }

        if ($this->image) {
            $html[] = "<meta property=\"og:image\" content=\"{$this->image}\">";
            $html[] = "<meta name=\"twitter:image\" content=\"{$this->image}\">";
        }

        if ($this->url) {
            $html[] = "<meta property=\"og:url\" content=\"{$this->url}\">";
            $html[] = "<link rel=\"canonical\" href=\"{$this->url}\">";
        }

        foreach ($this->metadata as $meta) {
            $html[] = "<meta {$meta['type']}=\"{$meta['name']}\" content=\"{$meta['content']}\">";
        }

        foreach ($this->jsonLd as $data) {
            $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $html[] = "<script type=\"application/ld+json\">{$json}</script>";
        }

        return implode("\n    ", $html);
    }

    public function __toString(): string
    {
        return $this->render();
    }
}
