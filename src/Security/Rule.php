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
     * Additional rules can be added here as static methods.
     */
}
