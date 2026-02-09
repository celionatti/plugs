<?php

declare(strict_types=1);

namespace Plugs\Container;

class Context
{
    private static ?Context $current = null;

    public function __construct(
        public readonly string $scope = 'root',
        public readonly array $tags = []
    ) {
    }

    public static function current(): self
    {
        return self::$current ??= new self();
    }

    public static function run(string $scope, callable $callback)
    {
        $previous = self::$current;
        self::$current = new self($scope);

        try {
            return $callback();
        } finally {
            self::$current = $previous;
        }
    }
}
