<?php

declare(strict_types=1);

use Plugs\Container\Container;

if (!function_exists('__')) {
    /**
     * Translate the given message.
     *
     * @param string|null $key
     * @param array $replace
     * @param string|null $locale
     * @return string|Plugs\Support\Translator
     */
    function __($key = null, $replace = [], $locale = null)
    {
        $translator = Container::getInstance()->make('translator');

        if (is_null($key)) {
            return $translator;
        }

        return $translator->get($key, $replace, $locale);
    }
}
