<?php

declare(strict_types=1);

namespace Plugs\Session;

/*
|--------------------------------------------------------------------------
| Session Handler Adapter
|--------------------------------------------------------------------------
|
| Adapts SessionDriverInterface implementations to PHP's
| SessionHandlerInterface for use with session_set_save_handler().
*/

class SessionHandler implements \SessionHandlerInterface
{
    public function __construct(
        private SessionDriverInterface $driver
    ) {
    }

    public function open(string $path, string $name): bool
    {
        return $this->driver->open($path, $name);
    }

    public function close(): bool
    {
        return $this->driver->close();
    }

    public function read(string $id): string|false
    {
        return $this->driver->read($id);
    }

    public function write(string $id, string $data): bool
    {
        return $this->driver->write($id, $data);
    }

    public function destroy(string $id): bool
    {
        return $this->driver->destroy($id);
    }

    public function gc(int $max_lifetime): int|false
    {
        return $this->driver->gc($max_lifetime);
    }
}
