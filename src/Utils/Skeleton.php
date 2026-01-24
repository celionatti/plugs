<?php

declare(strict_types=1);

namespace Plugs\Utils;

/**
 * Skeleton Loader Utility
 *
 * Provides methods to generate skeleton loading placeholders.
 */
class Skeleton
{
    private string $width = '100%';
    private string $height = '16px';
    private string $borderRadius = '4px';
    private string $margin = '0';
    private string $display = 'inline-block';
    private bool $pulse = false;
    private array $classes = [];
    private array $attributes = [];

    /**
     * Set width
     */
    public function width(string $width): self
    {
        $this->width = $width;

        return $this;
    }

    /**
     * Set height
     */
    public function height(string $height): self
    {
        $this->height = $height;

        return $this;
    }

    /**
     * Set border radius
     */
    public function radius(string $radius): self
    {
        $this->borderRadius = $radius;

        return $this;
    }

    /**
     * Set margin
     */
    public function margin(string $margin): self
    {
        $this->margin = $margin;

        return $this;
    }

    /**
     * Set display
     */
    public function display(string $display): self
    {
        $this->display = $display;

        return $this;
    }

    /**
     * Use pulse animation instead of shimmer
     */
    public function pulse(bool $pulse = true): self
    {
        $this->pulse = $pulse;

        return $this;
    }

    /**
     * Add extra classes
     */
    public function class(string $class): self
    {
        $this->classes[] = $class;

        return $this;
    }

    /**
     * Add data attributes or others
     */
    public function attr(string $key, string $value): self
    {
        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Render the skeleton
     */
    public function render(): string
    {
        $styles = sprintf(
            'width: %s; height: %s; border-radius: %s; margin: %s; display: %s;',
            $this->width,
            $this->height,
            $this->borderRadius,
            $this->margin,
            $this->display
        );

        $classes = array_merge(['plugs-skeleton'], $this->classes);
        if ($this->pulse) {
            $classes[] = 'plugs-skeleton-pulse';
        }

        $attrStr = '';
        foreach ($this->attributes as $key => $value) {
            $attrStr .= sprintf(' %s="%s"', $key, htmlspecialchars($value));
        }

        $html = sprintf(
            '<div class="%s" style="%s"%s></div>',
            implode(' ', $classes),
            $styles,
            $attrStr
        );

        $this->reset();

        return $html;
    }

    /**
     * Render a box (shorthand)
     */
    public function box(string $width = '100%', string $height = '16px', string $borderRadius = '4px'): self
    {
        return $this->width($width)->height($height)->radius($borderRadius);
    }

    /**
     * Render a circle
     */
    public function circle(string $size = '50px'): self
    {
        return $this->width($size)->height($size)->radius('50%');
    }

    /**
     * Render text line
     */
    public function text(string $width = '80%', string $height = '14px'): self
    {
        return $this->width($width)->height($height)->radius('3px');
    }

    /**
     * Render a card pre-set
     */
    public function card(bool $withImage = true): string
    {
        $html = '<div class="plugs-skeleton-card" style="border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; margin-bottom: 1rem;">';
        if ($withImage) {
            $html .= $this->height('150px')->margin('0 0 1rem 0')->render();
        }
        $html .= $this->width('60%')->margin('0 0 0.5rem 0')->render();
        $html .= $this->width('90%')->height('12px')->margin('0 0 0.5rem 0')->render();
        $html .= $this->width('40%')->height('12px')->render();
        $html .= '</div>';

        return $html;
    }

    /**
     * Render a list pre-set
     */
    public function list(int $rows = 3): string
    {
        $html = '<div class="plugs-skeleton-list">';
        for ($i = 0; $i < $rows; $i++) {
            $html .= '<div style="display: flex; align-items: center; margin-bottom: 1rem;">';
            $html .= $this->circle('40px')->margin('0 1rem 0 0')->render();
            $html .= '<div style="flex: 1;">';
            $html .= $this->width('50%')->margin('0 0 0.5rem 0')->render();
            $html .= $this->width('80%')->height('12px')->render();
            $html .= '</div>';
            $html .= '</div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Render a table pre-set
     */
    public function table(int $rows = 5, int $cols = 4): string
    {
        $html = '<div class="plugs-skeleton-table-wrapper" style="overflow-x: auto;">';
        $html .= '<table class="table">';
        $html .= '<thead><tr>';
        for ($i = 0; $i < $cols; $i++) {
            $html .= sprintf('<th>%s</th>', $this->width('80%')->height('20px')->render());
        }
        $html .= '</tr></thead>';
        $html .= '<tbody>';
        for ($r = 0; $r < $rows; $r++) {
            $html .= '<tr>';
            for ($c = 0; $c < $cols; $c++) {
                $html .= sprintf('<td>%s</td>', $this->width('100%')->height('15px')->render());
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        return $html;
    }

    private function reset(): void
    {
        $this->width = '100%';
        $this->height = '16px';
        $this->borderRadius = '4px';
        $this->margin = '0';
        $this->display = 'inline-block';
        $this->pulse = false;
        $this->classes = [];
        $this->attributes = [];
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Get the CSS styles required for skeleton animation.
     */
    public static function styles(): string
    {
        return '
        <style>
            .plugs-skeleton {
                background: #e2e8f0;
                background-image: linear-gradient(
                    90deg, 
                    rgba(255, 255, 255, 0) 0, 
                    rgba(255, 255, 255, 0.2) 20%, 
                    rgba(255, 255, 255, 0.5) 60%, 
                    rgba(255, 255, 255, 0)
                );
                background-size: 200% 100%;
                animation: plugs-shimmer 1.5s infinite;
                display: inline-block;
                vertical-align: middle;
            }

            .plugs-skeleton-pulse {
                animation: plugs-pulse 1.5s ease-in-out infinite;
                background-image: none !important;
            }

            @keyframes plugs-shimmer {
                0% { background-position: 200% 0; }
                100% { background-position: -200% 0; }
            }

            @keyframes plugs-pulse {
                0% { opacity: 1; }
                50% { opacity: 0.4; }
                100% { opacity: 1; }
            }

            [data-theme="dark"] .plugs-skeleton {
                background: #334155;
                background-image: linear-gradient(
                    90deg, 
                    rgba(255, 255, 255, 0) 0, 
                    rgba(255, 255, 255, 0.05) 20%, 
                    rgba(255, 255, 255, 0.1) 60%, 
                    rgba(255, 255, 255, 0)
                );
            }

            [data-theme="dark"] .plugs-skeleton-card {
                border-color: #334155 !important;
            }
        </style>';
    }
}
