<?php

declare(strict_types=1);

namespace Plugs\Security;

/*
|--------------------------------------------------------------------------
| Hash Class
|--------------------------------------------------------------------------
|
| This class is for hashing and verifying passwords securely.
*/

class Hash
{
    /**
     * Get the hashing algorithm from config
     *
     * @return string
     */
    private static function getAlgorithm(): string
    {
        $driver = config('hash.driver', 'argon2id');

        return match ($driver) {
            'bcrypt' => PASSWORD_BCRYPT,
            'argon' => PASSWORD_ARGON2I,
            'argon2id' => PASSWORD_ARGON2ID,
            default => PASSWORD_ARGON2ID,
        };
    }

    /**
     * Get the hashing options from config based on algorithm
     *
     * @return array
     */
    private static function getOptions(): array
    {
        $driver = config('hash.driver', 'argon2id');

        return match ($driver) {
            'bcrypt' => [
                'cost' => config('hash.bcrypt.rounds', 12),
            ],
            'argon' => [
                'memory_cost' => config('hash.argon.memory', 65536),
                'time_cost' => config('hash.argon.time', 4),
                'threads' => config('hash.argon.threads', 3),
            ],
            'argon2id' => [
                'memory_cost' => config('hash.argon2id.memory', 65536),
                'time_cost' => config('hash.argon2id.time', 4),
                'threads' => config('hash.argon2id.threads', 3),
            ],
            default => [
                'memory_cost' => 65536,
                'time_cost' => 4,
                'threads' => 3,
            ],
        };
    }

    /**
     * Hash a password
     *
     * @param string $password The password to hash
     * @return string The hashed password
     */
    public static function make(string $password): string
    {
        return password_hash(
            $password,
            self::getAlgorithm(),
            self::getOptions()
        );
    }

    /**
     * Verify a password against a hash
     *
     * @param string $password The plain text password
     * @param string $hash The hashed password
     * @return bool True if password matches hash
     */
    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    /**
     * Check if a hash needs to be rehashed
     *
     * @param string $hash The hashed password
     * @return bool True if hash needs rehashing
     */
    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash(
            $hash,
            self::getAlgorithm(),
            self::getOptions()
        );
    }

    /**
     * Verify password and automatically rehash if needed
     *
     * @param string $password The plain text password
     * @param string $hash The hashed password
     * @param callable|null $callback Optional callback to save new hash (receives new hash as parameter)
     * @return array ['verified' => bool, 'needs_rehash' => bool, 'new_hash' => string|null]
     */
    public static function verifyAndRehash(string $password, string $hash, ?callable $callback = null): array
    {
        $verified = self::verify($password, $hash);

        if (!$verified) {
            return [
                'verified' => false,
                'needs_rehash' => false,
                'new_hash' => null,
            ];
        }

        $needsRehash = self::needsRehash($hash);
        $newHash = null;

        if ($needsRehash && config('hash.verify.auto_rehash', false)) {
            $newHash = self::make($password);

            if ($callback !== null) {
                $callback($newHash);
            }
        }

        return [
            'verified' => true,
            'needs_rehash' => $needsRehash,
            'new_hash' => $newHash,
        ];
    }

    /**
     * Get information about a hashed password
     *
     * @param string $hash The hashed password
     * @return array Information about the hash
     */
    public static function info(string $hash): array
    {
        return password_get_info($hash);
    }

    /**
     * Check if the current driver is available
     *
     * @return bool
     */
    public static function isDriverAvailable(): bool
    {
        $algorithm = self::getAlgorithm();

        return in_array($algorithm, password_algos(), true);
    }

    /**
     * Get the current driver name
     *
     * @return string
     */
    public static function getDriver(): string
    {
        return config('hash.driver', 'argon2id');
    }
}
