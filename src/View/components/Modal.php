<?php

declare(strict_types=1);

namespace Plugs\View\Components;

use Plugs\View\Component;

/**
 * Built-in Modal Component (Class-Backed)
 *
 * Provides computed data for modal rendering.
 * The view template at src/View/components/modal.plug.php handles rendering.
 */
class Modal extends Component
{
    public string $id;
    public string $title = '';
    public string $size = 'md';
    public bool $closable = true;

    /**
     * Valid modal sizes
     */
    private const VALID_SIZES = ['sm', 'md', 'lg', 'xl'];

    public function __construct(
        ?string $id = null,
        string $title = '',
        string $size = 'md',
        bool $closable = true,
    ) {
        $this->id = $id ?? 'modal-' . substr(md5(uniqid()), 0, 8);
        $this->title = $title;
        $this->size = in_array($size, self::VALID_SIZES, true) ? $size : 'md';
        $this->closable = $closable;
    }

    public function render(): string
    {
        return 'modal';
    }
}
