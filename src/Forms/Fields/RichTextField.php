<?php

declare(strict_types=1);

namespace Plugs\Forms\Fields;

use Plugs\Forms\Field;

/**
 * Rich Text Editor Field using Quill.
 */
class RichTextField extends Field
{
    protected string $type = 'richtext';
    protected ?string $placeholder = null;
    protected ?int $maxImageWidth = null;
    protected ?int $maxImageHeight = null;

    /**
     * Set the placeholder for the editor.
     */
    public function placeholder(string $placeholder): self
    {
        $this->placeholder = $placeholder;
        return $this;
    }

    /**
     * Set the maximum image width.
     */
    public function maxImageWidth(int $width): self
    {
        $this->maxImageWidth = $width;
        return $this;
    }

    /**
     * Set the maximum image height.
     */
    public function maxImageHeight(int $height): self
    {
        $this->maxImageHeight = $height;
        return $this;
    }

    /**
     * Render the rich text editor field.
     */
    public function render(): string
    {
        $id = $this->getAttributes()['id'] ?? 'editor_' . $this->name;
        $inputId = 'input_' . $id;
        $value = htmlspecialchars((string) ($this->value ?? ''), ENT_QUOTES, 'UTF-8');
        $placeholderAttr = $this->placeholder ? sprintf(' data-placeholder="%s"', htmlspecialchars($this->placeholder)) : '';
        $widthAttr = $this->maxImageWidth ? sprintf(' data-max-width="%d"', $this->maxImageWidth) : '';
        $heightAttr = $this->maxImageHeight ? sprintf(' data-max-height="%d"', $this->maxImageHeight) : '';

        // We include the assets here. In a real app, you might want to move these to a layout or asset manager.
        $assets = $this->renderAssets();

        return sprintf(
            '%s<div class="plugs-editor-wrapper">
                <div id="%s" data-plugs-editor="%s"%s%s%s>%s</div>
                <input type="hidden" name="%s" id="%s" value="%s" %s>
            </div>',
            $assets,
            $id,
            $inputId,
            $placeholderAttr,
            $widthAttr,
            $heightAttr,
            $this->value, // Quill content is pre-sanitized or raw HTML from DB
            $this->name,
            $inputId,
            $value,
            $this->renderAttributes()
        );
    }

    /**
     * Render required assets (Quill CDN and local wrapper).
     */
    protected function renderAssets(): string
    {
        static $assetsRendered = false;

        if ($assetsRendered) {
            return '';
        }

        $assetsRendered = true;

        $css = [
            'https://cdn.quilljs.com/1.3.6/quill.snow.css',
            asset('assets/css/plugs-editor.css?v=' . time())
        ];

        $js = [
            'https://cdn.quilljs.com/1.3.6/quill.min.js',
            'https://cdn.jsdelivr.net/npm/quill-image-resize-module@3.0.0/image-resize.min.js',
            asset('assets/js/plugs-editor.js?v=' . time())
        ];

        $html = '';
        foreach ($css as $file) {
            $html .= sprintf('<link rel="stylesheet" href="%s">', $file);
        }
        foreach ($js as $file) {
            $html .= sprintf('<script src="%s"></script>', $file);
        }

        return $html;
    }
}
