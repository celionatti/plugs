<?php

declare(strict_types=1);

namespace Plugs\FeatureModule;

use Plugs\Container\Container;
use Plugs\Plugs;

/**
 * Convention-based feature module — created automatically when a module
 * directory exists but has no explicit {Name}Module.php class.
 *
 * Uses pure convention: routes from Routes/, migrations from Migrations/,
 * controllers namespaced under Modules\{Name}\Controllers.
 */
class ConventionModule extends AbstractFeatureModule
{
    protected string $name;

    public function __construct(string $name, string $path)
    {
        $this->name = $name;
        $this->path = rtrim($path, '/\\');
    }

    /**
     * {@inheritDoc}
     */
    public function getName(): string
    {
        return $this->name;
    }
}
