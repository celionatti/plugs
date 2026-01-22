<?php

declare(strict_types=1);

namespace Plugs\Security\OAuth;

use InvalidArgumentException;
use Plugs\Container\Container;
use Plugs\Security\OAuth\Drivers\GithubProvider;
use Plugs\Security\OAuth\Drivers\GoogleProvider;

class SocialiteManager
{
    protected $drivers = [];
    protected $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function driver(string $driver): AbstractProvider
    {
        if (!isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    protected function createDriver(string $driver): AbstractProvider
    {
        $config = config("services.{$driver}");

        if (!$config) {
            throw new InvalidArgumentException("Config not found for driver [{$driver}].");
        }

        switch ($driver) {
            case 'github':
                return new GithubProvider(
                    $config['client_id'],
                    $config['client_secret'],
                    $config['redirect']
                );
            case 'google':
                return new GoogleProvider(
                    $config['client_id'],
                    $config['client_secret'],
                    $config['redirect']
                );
            default:
                throw new InvalidArgumentException("Driver [{$driver}] not supported.");
        }
    }
}
