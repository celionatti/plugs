<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static string render(string $view, array $data = [], bool $isComponent = false)
 * @method static string renderComponent(string $componentName, array $data = [])
 * @method static void share(string $key, $value)
 * @method static bool exists(string $view)
 *
 * @see \Plugs\View\ViewEngineInterface
 * @see \Plugs\View\PlugViewEngine
 */
class View extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'view';
    }
}
