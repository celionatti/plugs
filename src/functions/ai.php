<?php

declare(strict_types=1);

use Plugs\AI\AIManager;

/*
|--------------------------------------------------------------------------
| AI Helper Function
|--------------------------------------------------------------------------
|
| Provides a global helper to access the AI manager instance.
*/

if (!function_exists('ai')) {
    /**
     * Get the AI manager instance.
     *
     * @return AIManager
     */
    function ai(): AIManager
    {
        return app(AIManager::class);
    }
}
