<?php

declare(strict_types=1);

namespace Plugs\Container;

use Psr\Container\NotFoundExceptionInterface;

/**
 * Container Not Found Exception
 *
 * Thrown when a binding cannot be found in the service container.
 */
class ContainerNotFoundException extends \Exception implements NotFoundExceptionInterface
{
}
