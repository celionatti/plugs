<?php

declare(strict_types=1);

namespace Plugs\Session;

/*
|--------------------------------------------------------------------------
| Session Driver Interface
|--------------------------------------------------------------------------
|
| Contract for all session storage backends. Implementations handle
| the actual read/write of session data to their respective stores.
*/

interface SessionDriverInterface
{
    /**
     * Open the session storage.
     *
     * @param string $savePath The path where sessions are stored
     * @param string $sessionName The session name
     * @return bool
     */
    public function open(string $savePath, string $sessionName): bool;

    /**
     * Close the session storage.
     *
     * @return bool
     */
    public function close(): bool;

    /**
     * Read session data.
     *
     * @param string $id The session ID
     * @return string The serialized session data
     */
    public function read(string $id): string;

    /**
     * Write session data.
     *
     * @param string $id The session ID
     * @param string $data The serialized session data
     * @return bool
     */
    public function write(string $id, string $data): bool;

    /**
     * Destroy a session.
     *
     * @param string $id The session ID
     * @return bool
     */
    public function destroy(string $id): bool;

    /**
     * Garbage collection — remove expired sessions.
     *
     * @param int $maxLifetime Maximum session lifetime in seconds
     * @return int|false The number of deleted sessions, or false on failure
     */
    public function gc(int $maxLifetime): int|false;
}
