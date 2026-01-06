<?php

declare(strict_types=1);

namespace Plugs\Utils;

/**
 * UUID and unique identifier generation utility
 */
class Uuid
{
    /**
     * Generate a UUID (version 1) - Time-based.
     */
    public static function v1(): string
    {
        $time = microtime(true) * 10000000 + 0x01b21dd213814000;
        $timeHex = str_pad(dechex((int) $time), 16, '0', STR_PAD_LEFT);

        $clockSeq = random_int(0, 0x3fff);
        $clockSeqHex = str_pad(dechex($clockSeq | 0x8000), 4, '0', STR_PAD_LEFT);

        $node = random_bytes(6);
        $nodeHex = bin2hex($node);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($timeHex, 8, 8),
            substr($timeHex, 4, 4),
            '1' . substr($timeHex, 1, 3),
            $clockSeqHex,
            $nodeHex
        );
    }

    /**
     * Generate a UUID (version 3) - Name-based MD5.
     */
    public static function v3(string $namespace, string $name): string
    {
        if (!static::isValid($namespace)) {
            return '';
        }

        $nhex = str_replace(['-', '{', '}'], '', $namespace);
        $nstr = '';

        for ($i = 0, $len = strlen($nhex); $i < $len; $i += 2) {
            $nstr .= chr((int) hexdec($nhex[$i] . $nhex[$i + 1]));
        }

        $hash = md5($nstr . $name);

        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x3000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    /**
     * Generate a UUID (version 4) - Random.
     */
    public static function v4(): string
    {
        $data = random_bytes(16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set variant to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Generate a UUID (version 5) - Name-based SHA-1.
     */
    public static function v5(string $namespace, string $name): string
    {
        if (!static::isValid($namespace)) {
            return '';
        }

        $nhex = str_replace(['-', '{', '}'], '', $namespace);
        $nstr = '';

        for ($i = 0, $len = strlen($nhex); $i < $len; $i += 2) {
            $nstr .= chr((int) hexdec($nhex[$i] . $nhex[$i + 1]));
        }

        $hash = sha1($nstr . $name);

        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0fff) | 0x5000,
            (hexdec(substr($hash, 16, 4)) & 0x3fff) | 0x8000,
            substr($hash, 20, 12)
        );
    }

    /**
     * Generate a UUID (version 7) - Unix Epoch time-based (sortable).
     */
    public static function v7(): string
    {
        $time = (int) (microtime(true) * 1000);
        $timeHex = str_pad(dechex($time), 12, '0', STR_PAD_LEFT);

        $data = random_bytes(10);
        $data[0] = chr(ord($data[0]) & 0x0f | 0x70); // version 7
        $data[2] = chr(ord($data[2]) & 0x3f | 0x80); // variant 10

        $randomHex = bin2hex($data);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($timeHex, 0, 8),
            substr($timeHex, 8, 4),
            substr($randomHex, 0, 4),
            substr($randomHex, 4, 4),
            substr($randomHex, 8, 12)
        );
    }

    /**
     * Generate a MongoDB-style ObjectID (12-byte hex string).
     * Structure: 4-byte timestamp, 5-byte random value, 3-byte counter.
     */
    public static function objectId(): string
    {
        static $counter = null;
        if ($counter === null) {
            $counter = random_int(0, 0xffffff);
        }

        $timestamp = time();
        $random = random_bytes(5);
        $count = $counter++ & 0xffffff;

        return sprintf(
            '%08x%s%06x',
            $timestamp,
            bin2hex($random),
            $count
        );
    }

    /**
     * Validate a UUID string.
     */
    public static function isValid(string $uuid): bool
    {
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-7][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $uuid) === 1;
    }

    /**
     * Proxy for backwards compatibility and easy access to standard random UUID.
     */
    public static function generate(): string
    {
        return static::v4();
    }
}
