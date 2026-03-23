<?php

declare(strict_types=1);

namespace Plugs\View\Components;

use Plugs\View\Component;

/**
 * Built-in Alert Component (Class-Backed)
 *
 * Provides computed CSS class/style data based on alert type.
 * The view template at src/View/components/alert.plug.php handles rendering.
 */
class Alert extends Component
{
    public string $type = 'info';
    public bool $dismissible = false;

    /**
     * Valid alert types
     */
    private const VALID_TYPES = ['success', 'danger', 'warning', 'info'];

    public function __construct(
        string $type = 'info',
        bool $dismissible = false,
    ) {
        $this->type = in_array($type, self::VALID_TYPES, true) ? $type : 'info';
        $this->dismissible = $dismissible;
    }

    public function render(): string
    {
        return 'alert';
    }
}
