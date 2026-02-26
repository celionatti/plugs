<?php

declare(strict_types=1);

namespace Plugs\Security;

use Plugs\Security\Rules\Unique;

class Rule
{
    /**
     * Get a unique constraint builder.
     *
     * @param string $table
     * @param string|null $column
     * @return \Plugs\Security\Rules\Unique
     */
    public static function unique(string $table, ?string $column = null): Unique
    {
        return new Unique($table, $column);
    }

    /**
     * Get a password rule instance.
     *
     * @return \Plugs\Security\Rules\Password
     */
    public static function password(): \Plugs\Security\Rules\Password
    {
        return new \Plugs\Security\Rules\Password();
    }

    /**
     * Get a dimensions constraint builder.
     */
    public static function dimensions(array $constraints = []): \Plugs\Security\Rules\Dimensions
    {
        return new \Plugs\Security\Rules\Dimensions($constraints);
    }

    /**
     * Get a mimetypes constraint builder.
     */
    public static function mimetypes(array $mimes = []): \Plugs\Security\Rules\Mimetypes
    {
        return new \Plugs\Security\Rules\Mimetypes($mimes);
    }

    /**
     * Get an exclude rule.
     */
    public static function exclude(): \Plugs\Security\Rules\Exclude
    {
        return new \Plugs\Security\Rules\Exclude();
    }

    /**
     * Get an exclude_if rule.
     */
    public static function excludeIf(string $field, $value): \Plugs\Security\Rules\ExcludeIf
    {
        return new \Plugs\Security\Rules\ExcludeIf($field, $value);
    }

    /**
     * Additional rules can be added here as static methods.
     */
}
