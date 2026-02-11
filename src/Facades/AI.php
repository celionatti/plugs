<?php

declare(strict_types=1);

namespace Plugs\Facades;

use Plugs\Facade;

/**
 * @method static string prompt(string $prompt, array $options = [])
 * @method static string chat(array $messages, array $options = [])
 * @method static \Plugs\AI\Contracts\AIDriverInterface withModel(string $model)
 * @method static \Plugs\AI\Contracts\AIDriverInterface driver(?string $name = null)
 * 
 * @see \Plugs\AI\AIManager
 */
class AI extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'ai';
    }
}
