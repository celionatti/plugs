<?php

declare(strict_types=1);

namespace Plugs\Module\Core;

use Plugs\Bootstrap\ContextType;
use Plugs\Container\Container;
use Plugs\Module\ModuleInterface;
use Plugs\Plugs;

class TranslatorModule implements ModuleInterface
{
    public function getName(): string
    {
        return 'Translator';
    }

    public function shouldBoot(ContextType $context): bool
    {
        return true;
    }

    public function register(Container $container): void
    {
        $container->singleton('translator', function () {
            $config = config('app');
            $locale = $config['locale'] ?? 'en';
            $fallback = $config['fallback_locale'] ?? 'en';
            $translator = new \Plugs\Support\Translator($locale, $fallback);
            $translator->addPath(base_path('resources/lang'));
            require_once dirname(__DIR__, 2) . '/functions/translation.php';
            return $translator;
        });
    }

    public function boot(Plugs $app): void
    {
    }
}
