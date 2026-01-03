<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Http\Browser as HttpBrowser;

/*
|--------------------------------------------------------------------------
| Browser Facade
|--------------------------------------------------------------------------
|
| This class provides a static interface to the Browser class.
*/

class Browser
{
    /** @var HttpBrowser|null */
    protected static $instance;

    /**
     * Get the Browser instance.
     *
     * @return HttpBrowser
     */
    protected static function getInstance(): HttpBrowser
    {
        if (!static::$instance) {
            static::$instance = new HttpBrowser();
        }

        return static::$instance;
    }

    /**
     * Handle dynamic static calls to the instance.
     *
     * @param string $method
     * @param array $args
     * @return mixed
     */
    public static function __callStatic($method, $args)
    {
        $instance = static::getInstance();

        return $instance->$method(...$args);
    }
}
