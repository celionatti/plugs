<?php

declare(strict_types=1);

namespace Plugs\Database\Traits;

use Plugs\Database\Attributes\QueryScope;
use Plugs\Database\ScopeProxy;
use ReflectionClass;
use ReflectionMethod;

trait HasScopes
{
    /**
     * Get all available query scopes on the model.
     *
     * @return array<string, array{method: string, description: ?string}>
     */
    public static function getAvailableScopes(): array
    {
        $scopes = [];
        $reflection = new ReflectionClass(static::class);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $attributes = $method->getAttributes(QueryScope::class);
            if (!empty($attributes)) {
                $instance = $attributes[0]->newInstance();
                $name = $method->getName();

                // If it starts with 'scope', we can use the short name for discovery
                $shortName = $name;
                if (str_starts_with($name, 'scope') && strlen($name) > 5) {
                    $shortName = lcfirst(substr($name, 5));
                }

                $scopes[$shortName] = [
                    'method' => $name,
                    'description' => $instance->description,
                ];
            }
        }

        return $scopes;
    }

    /**
     * Get a scope proxy for the current query.
     *
     * @return ScopeProxy
     */
    public function scoped(): ScopeProxy
    {
        return new ScopeProxy($this->query());
    }
}
