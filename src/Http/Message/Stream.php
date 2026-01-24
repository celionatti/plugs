<?php

declare(strict_types=1);

namespace Plugs\Http\Message;

/*
|--------------------------------------------------------------------------
| Stream Class
|--------------------------------------------------------------------------
|
| This class represents a stream used in HTTP messages. It provides methods
| to read from, write to, and manage the stream resource.
*/

use Psr\Http\Message\StreamInterface;

class Stream implements StreamInterface
{
    private $resource;
    private $seekable;
    private $readable;
    private $writable;

    public function __construct($resource)
    {
        if (!is_resource($resource)) {
            throw new \InvalidArgumentException('Stream must be a resource');
        }

        $this->resource = $resource;
        $meta = stream_get_meta_data($resource);
        $this->seekable = $meta['seekable'];

        $mode = $meta['mode'];
        $this->readable = $this->isReadableMode($mode);
        $this->writable = $this->isWritableMode($mode);
    }

    private function isReadableMode(string $mode): bool
    {
        return (strpos($mode, 'r') !== false || strpos($mode, '+') !== false);
    }

    private function isWritableMode(string $mode): bool
    {
        return (
            strpos($mode, 'w') !== false ||
            strpos($mode, 'a') !== false ||
            strpos($mode, 'x') !== false ||
            strpos($mode, 'c') !== false ||
            strpos($mode, '+') !== false
        );
    }

    public function __toString(): string
    {
        if (!$this->isReadable()) {
            return '';
        }

        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }

            return $this->getContents();
        } catch (\RuntimeException $e) {
            return '';
        }
    }

    public function close(): void
    {
        if (isset($this->resource)) {
            fclose($this->resource);
        }
        $this->detach();
    }

    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        $this->readable = false;
        $this->writable = false;
        $this->seekable = false;

        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);

        return $stats['size'] ?? null;
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        $result = ftell($this->resource);
        if ($result === false) {
            throw new \RuntimeException('Unable to determine stream position');
        }

        return $result;
    }

    public function eof(): bool
    {
        return $this->resource ? feof($this->resource) : true;
    }

    public function isSeekable(): bool
    {
        return $this->seekable;
    }

    public function seek($offset, $whence = SEEK_SET): void
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isSeekable()) {
            throw new \RuntimeException('Stream is not seekable');
        }

        if (fseek($this->resource, $offset, $whence) === -1) {
            throw new \RuntimeException('Unable to seek to stream position');
        }
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->writable;
    }

    public function write($string): int
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isWritable()) {
            throw new \RuntimeException('Cannot write to a non-writable stream');
        }

        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new \RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    public function isReadable(): bool
    {
        return $this->readable;
    }

    // FIXED: Return string instead of int
    public function read($length): string
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isReadable()) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        $result = fread($this->resource, $length);
        if ($result === false) {
            throw new \RuntimeException('Unable to read from stream');
        }

        return $result;
    }

    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new \RuntimeException('Stream is detached');
        }

        if (!$this->isReadable()) {
            throw new \RuntimeException('Cannot read from non-readable stream');
        }

        $result = stream_get_contents($this->resource);
        if ($result === false) {
            throw new \RuntimeException('Unable to read stream contents');
        }

        return $result;
    }

    public function getMetadata($key = null)
    {
        if ($this->resource === null) {
            return $key ? null : [];
        }

        $meta = stream_get_meta_data($this->resource);

        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
}
