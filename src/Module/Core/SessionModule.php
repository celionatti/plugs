<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class SessionModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Session';
    }

    public function shouldBoot(ContextType $context): bool
    {
        // Session only boots in Web context
        return $context === ContextType::Web;
    }

    public function register(Container $container): void
    {
        // Session isn't typically registered as a deferred singleton in Plugs.php,
        // it's configured in the kernel. We can register it here.
    }

    public function boot(Plugs $app): void
    {
        $sessionConfig = config('security.session');
        if ($sessionConfig) {
            $sessionLoader = new \Plugs\Session\SessionManager($sessionConfig);
            $sessionLoader->start();
        }
    }
}
