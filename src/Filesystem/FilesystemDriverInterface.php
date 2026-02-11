<?php

namespace Plugs\Filesystem;

interface FilesystemDriverInterface
{
    /**
     * Determine if a file exists.
     *
     * @param string $path
     * @return bool
     */
    public function exists(string $path): bool;

    /**
     * Get the contents of a file.
     *
     * @param string $path
     * @return string|null
     */
    public function get(string $path): ?string;

    /**
     * Write the contents of a file.
     *
     * @param string $path
     * @param string $contents
     * @return bool
     */
    public function put(string $path, string $contents): bool;

    /**
     * Delete the file at a given path.
     *
     * @param string $path
     * @return bool
     */
    public function delete(string $path): bool;

    /**
     * Get the URL for the file at the given path.
     *
     * @param string $path
     * @return string
     */
    public function url(string $path): string;

    /**
     * Get the file size of a given file.
     *
     * @param string $path
     * @return int
     */
    public function size(string $path): int;

    /**
     * Get the last modified time of a given file.
     *
     * @param string $path
     * @return int
     */
    public function lastModified(string $path): int;

    /**
     * Create a directory.
     *
     * @param string $path
     * @return bool
     */
    public function makeDirectory(string $path): bool;

    /**
     * Delete a directory.
     *
     * @param string $path
     * @return bool
     */
    public function deleteDirectory(string $path): bool;

    /**
     * Create a streamed response for a file download.
     *
     * @param string $path
     * @param string|null $name
     * @param array $headers
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function download(string $path, ?string $name = null, array $headers = []);

    /**
     * Get the full path for the given relative path.
     *
     * @param string $path
     * @return string
     */
    public function fullPath(string $path): string;

    /**
     * Get the relative path for the given absolute path.
     *
     * @param string $absolutePath
     * @return string
     */
    public function path(string $absolutePath): string;

    /**
     * Copy a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function copy(string $from, string $to): bool;

    /**
     * Move a file to a new location.
     *
     * @param string $from
     * @param string $to
     * @return bool
     */
    public function move(string $from, string $to): bool;

    /**
     * Get the visibility for the given path.
     *
     * @param string $path
     * @return string
     */
    public function getVisibility(string $path): string;

    /**
     * Set the visibility for the given path.
     *
     * @param string $path
     * @param string $visibility
     * @return bool
     */
    public function setVisibility(string $path, string $visibility): bool;

    /**
     * Get the mime-type of a given file.
     *
     * @param string $path
     * @return string
     */
    public function mimeType(string $path): string;

    /**
     * Append to a file.
     *
     * @param string $path
     * @param string $data
     * @return bool
     */
    public function append(string $path, string $data): bool;

    /**
     * Prepend to a file.
     *
     * @param string $path
     * @param string $data
     * @return bool
     */
    public function prepend(string $path, string $data): bool;
}
