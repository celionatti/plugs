<?php

declare(strict_types=1);

namespace Plugs\Session\Drivers;

use Plugs\Session\SessionDriverInterface;

/*
|--------------------------------------------------------------------------
| File Session Driver
|--------------------------------------------------------------------------
|
| Default session driver using PHP's native file-based session handling.
| This wraps PHP's built-in file session handler to comply with the
| SessionDriverInterface.
*/

class FileSessionDriver implements SessionDriverInterface
{
    private string $savePath;

    public function __construct(?string $savePath = null)
    {
        $this->savePath = $savePath ?? sys_get_temp_dir();
    }

    public function open(string $savePath, string $sessionName): bool
    {
        $this->savePath = $savePath ?: $this->savePath;

        if (!is_dir($this->savePath)) {
            mkdir($this->savePath, 0755, true);
        }

        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string
    {
        $file = $this->getFilePath($id);

        return file_exists($file) ? (string) file_get_contents($file) : '';
    }

    public function write(string $id, string $data): bool
    {
        return file_put_contents($this->getFilePath($id), $data, LOCK_EX) !== false;
    }

    public function destroy(string $id): bool
    {
        $file = $this->getFilePath($id);

        if (file_exists($file)) {
            return unlink($file);
        }

        return true;
    }

    public function gc(int $maxLifetime): int|false
    {
        $count = 0;
        $cutoff = time() - $maxLifetime;

        foreach (glob($this->savePath . '/sess_*') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    private function getFilePath(string $id): string
    {
        return $this->savePath . '/sess_' . $id;
    }
}
